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
  const result = await db.query(
    `SELECT c.id,
            c.name,
            c.default_caller_id,
            c.sip_domain,
            c.sip_port,
            c.transport,
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
};

const createCarrier = async ({
  name,
  callerId,
  sipDomain,
  sipPort,
  transport,
  registrationRequired,
  registrationUsername,
  registrationPassword
}) => {
  const result = await db.query(
    `INSERT INTO carriers (name, default_caller_id, sip_domain, sip_port, transport, registration_required, registration_username, registration_password)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
     RETURNING id, name, default_caller_id, sip_domain, sip_port, transport, registration_required, registration_username, registration_password`,
    [
      name,
      callerId,
      sipDomain,
      sipPort || null,
      transport || 'udp',
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
  { name, callerId, sipDomain, sipPort, transport, registrationRequired, registrationUsername, registrationPassword }
) => {
  const result = await db.query(
    `UPDATE carriers
     SET name = COALESCE($2, name),
         default_caller_id = COALESCE($3, default_caller_id),
         sip_domain = COALESCE($4, sip_domain),
         sip_port = COALESCE($5, sip_port),
         transport = COALESCE($6, transport),
         registration_required = COALESCE($7, registration_required),
         registration_username = COALESCE($8, registration_username),
         registration_password = COALESCE(NULLIF($9, ''), registration_password)
     WHERE id = $1
     RETURNING id, name, default_caller_id, sip_domain, sip_port, transport, registration_required, registration_username, registration_password`,
    [
      carrierId,
      name,
      callerId,
      sipDomain,
      sipPort,
      transport,
      registrationRequired,
      registrationUsername,
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

const deleteCarrier = async (carrierId) => {
  const existing = await db.query('SELECT id, name FROM carriers WHERE id = $1', [carrierId]);
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
            sip_domain,
            sip_port,
            transport,
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
