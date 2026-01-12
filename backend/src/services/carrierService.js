const db = require('../db');
const freeswitch = require('../lib/freeswitch');
const { normalizeGatewayName } = require('../lib/carrierUtils');
const { syncGateway, removeGateway } = require('../lib/gatewayConfig');

const buildRegistrationStatus = async (carrier) => {
  if (!carrier || !carrier.registration_required) {
    return { state: 'not_required', label: 'Not Required' };
  }

  const gatewayName = normalizeGatewayName(carrier);
  if (!gatewayName) {
    return { state: 'pending', label: 'Pending', detail: 'Invalid carrier name' };
  }

  try {
    const gatewayStatus = await freeswitch.getGatewayStatus(gatewayName);
    const normalizedState = gatewayStatus?.state ? gatewayStatus.state.toUpperCase() : null;
    if (normalizedState === 'REGED') {
      return {
        state: 'success',
        label: gatewayStatus.status || '200 OK',
        detail: gatewayStatus.state
      };
    }

    const detail = gatewayStatus?.status || gatewayStatus?.state || 'Failed';
    return {
      state: 'error',
      label: detail,
      detail
    };
  } catch (err) {
    return {
      state: 'error',
      label: err.message || 'Failed'
    };
  }
};

const triggerRegistration = async (carrier) => {
  if (!carrier || !carrier.registration_required) {
    return;
  }
  const gatewayName = normalizeGatewayName(carrier);
  if (!gatewayName) {
    return;
  }
  try {
    await freeswitch.registerGateway(gatewayName);
  } catch (err) {
    // Swallow errors; registration status call will reflect failures.
  }
};

const hydrateCarrier = async (carrier) => ({
  ...carrier,
  registration_status: await buildRegistrationStatus(carrier)
});

const listCarriers = async () => {
  try {
    const result = await db.query(
      `SELECT c.id,
              c.name,
              c.default_caller_id,
              c.caller_id_required,
              c.sip_domain,
              c.sip_port,
              c.transport,
              c.outbound_proxy,
              c.registration_required,
              c.registration_username,
              json_agg(
                json_build_object(
                  'id', p.id,
                  'prefix', p.prefix,
                  'callerId', p.caller_id
                )
              ) FILTER (WHERE p.id IS NOT NULL) AS prefixes
       FROM carriers c
       LEFT JOIN carrier_prefixes p ON p.carrier_id = c.id
       GROUP BY c.id
       ORDER BY c.created_at DESC`
    );
    return Promise.all(result.rows.map(hydrateCarrier));
  } catch (err) {
    if (err && (err.code === '42P01' || err.code === '42703')) {
      const fallback = await db.query(
        `SELECT *
         FROM carriers
         ORDER BY created_at DESC`
      );
      return Promise.all(
        fallback.rows.map((row) =>
          hydrateCarrier({
            ...row,
            caller_id_required: row.caller_id_required ?? false,
            transport: row.transport || 'udp',
            outbound_proxy: row.outbound_proxy || null,
            registration_required: row.registration_required ?? false,
            prefixes: []
          })
        )
      );
    }
    throw err;
  }
};

const createCarrier = async ({
  name,
  callerId,
  callerIdRequired,
  sipDomain,
  sipPort,
  transport,
  registrationRequired,
  registrationUsername,
  registrationPassword,
  outboundProxy
}) => {
  const result = await db.query(
    `INSERT INTO carriers (name, default_caller_id, caller_id_required, sip_domain, sip_port, transport, outbound_proxy, registration_required, registration_username, registration_password)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
     RETURNING id, name, default_caller_id, caller_id_required, sip_domain, sip_port, transport, outbound_proxy, registration_required, registration_username, registration_password`,
    [
      name,
      callerId || null,
      callerIdRequired !== undefined ? callerIdRequired : false,
      sipDomain,
      sipPort || null,
      transport || 'udp',
      outboundProxy || null,
      registrationRequired || false,
      registrationUsername || null,
      registrationPassword || null
    ]
  );
  const carrier = result.rows[0];
  await syncGateway({
    ...carrier,
    registration_password: carrier.registration_password
  });
  await triggerRegistration(carrier);
  delete carrier.registration_password;
  return hydrateCarrier(carrier);
};

const updateCarrier = async (
  carrierId,
  {
    name,
    callerId,
    callerIdRequired,
    sipDomain,
    sipPort,
    transport,
    registrationRequired,
    registrationUsername,
    registrationPassword,
    outboundProxy
  }
) => {
  const shouldUpdateCallerId = callerId !== undefined;
  const normalizedCallerId = callerId || null;
  const result = await db.query(
    `UPDATE carriers
     SET name = COALESCE($2, name),
         default_caller_id = CASE WHEN $12 THEN $3 ELSE default_caller_id END,
         caller_id_required = COALESCE($4, caller_id_required),
         sip_domain = COALESCE($5, sip_domain),
         sip_port = COALESCE($6, sip_port),
         transport = COALESCE($7, transport),
         outbound_proxy = COALESCE($8, outbound_proxy),
         registration_required = COALESCE($9, registration_required),
         registration_username = COALESCE($10, registration_username),
         registration_password = COALESCE(NULLIF($11, ''), registration_password)
     WHERE id = $1
     RETURNING id, name, default_caller_id, caller_id_required, sip_domain, sip_port, transport, outbound_proxy, registration_required, registration_username, registration_password`,
    [
      carrierId,
      name,
      normalizedCallerId,
      callerIdRequired,
      sipDomain,
      sipPort,
      transport,
      outboundProxy,
      registrationRequired,
      registrationUsername,
      registrationPassword || null,
      shouldUpdateCallerId
    ]
  );
  const carrier = result.rows[0];
  await syncGateway({
    ...carrier,
    registration_password: carrier.registration_password
  });
  await triggerRegistration(carrier);
  delete carrier.registration_password;
  return hydrateCarrier(carrier);
};

const deleteCarrier = async (carrierId) => {
  const existing = await db.query('SELECT id, name FROM carriers WHERE id = $1', [carrierId]);
  const usage = await db.query('SELECT COUNT(*)::int AS total FROM users WHERE carrier_id = $1', [carrierId]);
  if ((usage.rows[0]?.total || 0) > 0) {
    const err = new Error('Carrier is assigned to one or more users. Reassign users before deleting.');
    err.code = 'CARRIER_IN_USE';
    throw err;
  }
  await db.query('DELETE FROM carriers WHERE id = $1', [carrierId]);
  if (existing.rowCount > 0) {
    await removeGateway(existing.rows[0]);
  }
};

const getCarrierById = async (carrierId) => {
  const result = await db.query(
    `SELECT id,
            name,
            default_caller_id,
            caller_id_required,
            sip_domain,
            sip_port,
            transport,
            outbound_proxy,
            registration_required,
            registration_username
     FROM carriers
     WHERE id = $1`,
    [carrierId]
  );
  const carrier = result.rows[0];
  if (!carrier) {
    return null;
  }
  return hydrateCarrier(carrier);
};

const addPrefix = async ({ carrierId, prefix, callerId }) => {
  const result = await db.query(
    `INSERT INTO carrier_prefixes (carrier_id, prefix, caller_id)
     VALUES ($1, $2, $3)
     RETURNING id, carrier_id, prefix, caller_id`,
    [carrierId, prefix, callerId]
  );
  return result.rows[0];
};

module.exports = {
  listCarriers,
  createCarrier,
  updateCarrier,
  deleteCarrier,
  addPrefix,
  getCarrierById
};
