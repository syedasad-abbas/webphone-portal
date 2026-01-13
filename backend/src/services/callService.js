const { randomUUID } = require('crypto');
const db = require('../db');
const freeswitch = require('../lib/freeswitch');
const { normalizeGatewayName } = require('../lib/carrierUtils');
const config = require('../config');

const normalizeDestination = (destination) => {
  if (!destination) {
    return destination;
  }
  const digits = destination.toString().replace(/\D+/g, '');
  const isUsNumber = digits.length === 10 || (digits.length === 11 && digits.startsWith('1'));
  if (!isUsNumber) {
    return null;
  }
  return digits.startsWith('1') ? `+${digits}` : `+1${digits}`;
};

const normalizeCallerId = (callerId) => {
  if (!callerId) {
    return null;
  }
  const digits = callerId.toString().replace(/\D+/g, '');
  if (!digits) {
    return null;
  }
  if (digits.length === 10) {
    return `+1${digits}`;
  }
  if (digits.length === 11 && digits.startsWith('1')) {
    return `+${digits}`;
  }
  return `+${digits}`;
};

const stripPlus = (value) => {
  if (!value) {
    return value;
  }
  return value.toString().replace(/^\+/, '');
};

const logCall = async ({ userId, destination, callerId, status, recordingPath, callUuid, connectedAt, endedAt }) => {
  await db.query(
    `INSERT INTO call_logs (user_id, destination, caller_id, status, recording_path, call_uuid, connected_at, ended_at)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
    [userId, destination, callerId, status, recordingPath, callUuid, connectedAt || null, endedAt || null]
  );
};

const selectCarrierPrefix = (prefixes) => {
  if (!Array.isArray(prefixes) || prefixes.length === 0) {
    return null;
  }
  return prefixes.find((entry) => entry.prefix) || null;
};

const applyDialPrefix = (normalizedDestination, prefixEntry) => {
  if (!prefixEntry?.prefix) {
    return normalizedDestination;
  }
  const withoutPlus = (normalizedDestination || '').replace(/^\+/, '');
  const prefixDigits = prefixEntry.prefix.toString().replace(/\D+/g, '');
  return `${prefixDigits}${withoutPlus}`;
};

const originate = async ({ user, destination, callerId }) => {
  const originationUuid = randomUUID();
  const normalizedDestination = normalizeDestination(destination);
  if (!normalizedDestination) {
    throw new Error('Only US/CA destinations (+1) are allowed');
  }
  const userResult = await db.query(
    `SELECT users.id,
            users.carrier_id,
            users.full_name,
            users.recording_enabled,
            carriers.default_caller_id,
            carriers.caller_id_required,
            carriers.sip_domain,
            carriers.sip_port,
            carriers.transport,
            carriers.outbound_proxy,
            carriers.registration_required,
            carriers.registration_username,
            carriers.registration_password
     FROM users
     LEFT JOIN carriers ON carriers.id = users.carrier_id
     WHERE users.id = $1`,
    [user.id]
  );

  if (userResult.rowCount === 0) {
    throw new Error('User not found');
  }

  const record = userResult.rows[0];
  const fallbackCallerId = config.defaults.carrierCallerId || null;
  let prefixEntries = [];
  if (record.carrier_id) {
    const prefixResult = await db.query(
      `SELECT prefix, caller_id
         FROM carrier_prefixes
        WHERE carrier_id = $1
        ORDER BY created_at ASC`,
      [record.carrier_id]
    );
    prefixEntries = prefixResult.rows || [];
  }
  const selectedPrefix = selectCarrierPrefix(prefixEntries, normalizedDestination);
  const prefixedDestination = applyDialPrefix(normalizedDestination, selectedPrefix);
  const prefixCallerId = selectedPrefix?.caller_id || null;
  let resolvedCallerId = callerId || prefixCallerId || record.default_caller_id || null;
  if (!resolvedCallerId && record.caller_id_required) {
    resolvedCallerId = fallbackCallerId;
  }
  const callerIdUser = normalizeCallerId(resolvedCallerId);
  const callerIdSipUser = stripPlus(callerIdUser);
  const recordingEnabled = record.recording_enabled;
  const recordingPath = recordingEnabled
    ? `${config.freeswitch.recordingsPath}/${user.id}-${Date.now()}.wav`
    : null;

  if (!record.sip_domain) {
    throw new Error('Carrier domain is not configured');
  }

  const domainPart = record.sip_port ? `${record.sip_domain}:${record.sip_port}` : record.sip_domain;
  const useGateway = Boolean(record.registration_required || record.outbound_proxy);
  const gatewayName = useGateway ? normalizeGatewayName({ id: record.carrier_id }) : null;
  if (useGateway && !gatewayName) {
    throw new Error('Carrier gateway is not configured');
  }
  const fromHostBase = useGateway
    ? config.freeswitch.externalSipIp || record.sip_domain || null
    : record.sip_domain || config.freeswitch.externalSipIp || null;
  const fromHostWithPort = fromHostBase && record.sip_port && useGateway
    ? `${fromHostBase}:${record.sip_port}`
    : fromHostBase;
  const toUser = useGateway ? (prefixedDestination || normalizedDestination) : prefixedDestination || normalizedDestination;
  const requestUser = toUser;
  const endpoint = useGateway ? null : `sofia/external/${requestUser}@${domainPart}`;
  const transport = (record.transport || 'udp').toLowerCase();
  const channelVars = [
    `sip_transport=${transport}`,
    `origination_uuid=${originationUuid}`,
    `sip_req_user=${requestUser}`,
    `sip_to_user=${toUser}`
  ];
  if (useGateway && record.sip_domain) {
    channelVars.push(`sip_req_host=${record.sip_domain}`);
    if (record.sip_port) {
      channelVars.push(`sip_req_port=${record.sip_port}`);
    }
  }
  if (!useGateway) {
    channelVars.push(`sip_to_host=${domainPart}`);
  }
  const authFromUser = callerIdSipUser || (useGateway ? record.registration_username : null);
  if (authFromUser) {
    channelVars.push(`sip_from_user=${authFromUser}`);
  }
  if (fromHostBase) {
    channelVars.push(`sip_from_host=${fromHostBase}`);
  }
  if (useGateway && record.sip_port) {
    channelVars.push(`sip_from_port=${record.sip_port}`);
  }
  if (fromHostWithPort) {
    const identityUser = callerIdSipUser || authFromUser;
    if (identityUser) {
      const pai = `<sip:${identityUser}@${fromHostWithPort}>`;
      channelVars.push(`sip_from_uri=sip:${identityUser}@${fromHostWithPort}`);
      channelVars.push(`sip_h_P-Asserted-Identity=${pai}`);
      channelVars.push(`sip_h_P-Preferred-Identity=${pai}`);
    }
  }
  // Disable Remote-Party-ID to avoid carriers that reject it.
  channelVars.push('sip_rpid_type=none');
  if (record.registration_username) {
    channelVars.push(`sip_auth_username=${record.registration_username}`);
  }
  if (record.registration_password) {
    channelVars.push(`sip_auth_password=${record.registration_password}`);
  }

  let jobUuid = null;
  try {
    console.log('[call] originate', {
      userId: user.id,
      destination: normalizedDestination,
      requestUser,
      toUser,
      callerId: resolvedCallerId,
      sipHost: domainPart,
      transport
    });
    const originateResult = await freeswitch.originateCall({
      endpoint,
      callerId: callerIdSipUser,
      recordingPath,
      variables: channelVars,
      gateway: useGateway ? gatewayName : null,
      destination: useGateway ? toUser : null
    });
    jobUuid = originateResult.jobUuid || originationUuid;
    await logCall({
      userId: user.id,
      destination: normalizedDestination,
      callerId: resolvedCallerId,
      status: 'queued',
      recordingPath,
      callUuid: originationUuid
    });
    return { status: 'queued', callUuid: originationUuid };
  } catch (err) {
    console.error('[call] originate failed', {
      userId: user.id,
      destination: normalizedDestination,
      requestUser,
      toUser,
      callerId: resolvedCallerId,
      sipHost: domainPart,
      transport,
      error: err.message
    });
    await logCall({
      userId: user.id,
      destination: normalizedDestination,
      callerId: resolvedCallerId,
      status: 'failed',
      recordingPath,
      callUuid: originationUuid
    });
    throw err;
  }
};

module.exports = {
  originate
};
