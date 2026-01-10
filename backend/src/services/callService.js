const { randomUUID } = require('crypto');
const db = require('../db');
const freeswitch = require('../lib/freeswitch');
const config = require('../config');

const normalizeDestination = (destination) => {
  if (!destination) {
    return destination;
  }
  const digits = destination.toString().replace(/\D+/g, '');
  return digits.startsWith('1') ? `+${digits}` : `+1${digits}`;
};

const logCall = async ({ userId, destination, callerId, status, recordingPath, callUuid, connectedAt, endedAt }) => {
  await db.query(
    `INSERT INTO call_logs (user_id, destination, caller_id, status, recording_path, call_uuid, connected_at, ended_at)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
    [userId, destination, callerId, status, recordingPath, callUuid, connectedAt || null, endedAt || null]
  );
};

const selectCarrierPrefix = () => null;

const applyDialPrefix = (normalizedDestination, prefixEntry) => {
  if (!prefixEntry?.prefix) {
    return normalizedDestination;
  }
  const digits = (normalizedDestination || '').replace(/^\+/, '');
  return `${prefixEntry.prefix}${digits}`;
};

const originate = async ({ user, destination, callerId }) => {
  const originationUuid = randomUUID();
  const normalizedDestination = normalizeDestination(destination);
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
  const recordingEnabled = record.recording_enabled;
  const recordingPath = recordingEnabled
    ? `${config.freeswitch.recordingsPath}/${user.id}-${Date.now()}.wav`
    : null;

  if (!record.sip_domain) {
    throw new Error('Carrier domain is not configured');
  }

  const domainPart = record.sip_port ? `${record.sip_domain}:${record.sip_port}` : record.sip_domain;
  const toUser = normalizedDestination;
  const requestUser = prefixedDestination || normalizedDestination;
  const endpoint = `sofia/external/${requestUser}@${domainPart}`;
  const transport = (record.transport || 'udp').toLowerCase();
  const channelVars = [
    `sip_transport=${transport}`,
    `origination_uuid=${originationUuid}`,
    `sip_req_user=${requestUser}`,
    `sip_to_user=${toUser}`,
    `sip_to_host=${domainPart}`
  ];
  if (resolvedCallerId) {
    channelVars.push(`sip_from_user=${resolvedCallerId}`);
  }
  if (config.freeswitch.externalSipIp) {
    channelVars.push(`sip_from_host=${config.freeswitch.externalSipIp}`);
  }
  if (record.registration_username) {
    channelVars.push(`sip_auth_username=${record.registration_username}`);
  }
  if (record.registration_password) {
    channelVars.push(`sip_auth_password=${record.registration_password}`);
  }

  let jobUuid = null;
  try {
    const originateResult = await freeswitch.originateCall({
      endpoint,
      callerId: resolvedCallerId,
      recordingPath,
      variables: channelVars
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
