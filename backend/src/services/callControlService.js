const db = require('../db');
const freeswitch = require('../lib/freeswitch');

const findCallByUuid = async (uuid, userId) => {
  const result = await db.query(
    'SELECT * FROM call_logs WHERE call_uuid = $1 AND user_id = $2 ORDER BY created_at DESC LIMIT 1',
    [uuid, userId]
  );
  if (result.rowCount === 0) {
    throw new Error('Call not found');
  }
  return result.rows[0];
};

const updateCallCompletion = async (callId, durationSeconds) => {
  await db.query(
    `UPDATE call_logs
     SET status = 'completed',
         ended_at = COALESCE(ended_at, NOW()),
         duration_seconds = COALESCE(duration_seconds, $1)
     WHERE id = $2`,
    [durationSeconds || null, callId]
  );
};

const updateCallDiagnostics = async (callId, diagnostics) => {
  await db.query(
    `UPDATE call_logs
     SET sip_status = COALESCE($1, sip_status),
         sip_reason = COALESCE($2, sip_reason),
         hangup_cause = COALESCE($3, hangup_cause)
     WHERE id = $4`,
    [
      diagnostics.sipStatus ?? null,
      diagnostics.sipReason ?? null,
      diagnostics.hangupCause ?? null,
      callId
    ]
  );
};

const fetchCallDiagnostics = async (uuid) => {
  const sipStatusCandidates = await Promise.all([
    freeswitch.getChannelVar(uuid, 'sip_last_status'),
    freeswitch.getChannelVar(uuid, 'sip_call_status'),
    freeswitch.getChannelVar(uuid, 'sip_term_status')
  ]);
  const sipReason = await freeswitch.getChannelVar(uuid, 'sip_term_phrase');
  const hangupCause = await freeswitch.getChannelVar(uuid, 'hangup_cause');
  const sipStatus = sipStatusCandidates
    .map((value) => (value && !Number.isNaN(Number(value)) ? Number(value) : null))
    .find((value) => value !== null) ?? null;
  return {
    sipStatus,
    sipReason: sipReason || null,
    hangupCause: hangupCause || null
  };
};

const getStatus = async ({ uuid, userId }) => {
  const call = await findCallByUuid(uuid, userId);
  const exists = await freeswitch.callExists(uuid);
  const diagnostics = await fetchCallDiagnostics(uuid);
  if (diagnostics.sipStatus || diagnostics.sipReason || diagnostics.hangupCause) {
    await updateCallDiagnostics(call.id, diagnostics);
  }

  if (!exists) {
    await updateCallCompletion(call.id, call.duration_seconds);
    return {
      status: call.status === 'completed' ? 'completed' : 'ended',
      sipStatus: diagnostics.sipStatus ?? call.sip_status ?? null,
      sipReason: diagnostics.sipReason ?? call.sip_reason ?? null,
      hangupCause: diagnostics.hangupCause ?? call.hangup_cause ?? null,
      recordingPath: call.recording_path,
      durationSeconds: call.duration_seconds || 0
    };
  }

  const answeredEpoch = await freeswitch.getChannelVar(uuid, 'answered_epoch');
  const billsec = await freeswitch.getChannelVar(uuid, 'billsec');

  const answered = answeredEpoch && Number(answeredEpoch) > 0;
  const durationSeconds = billsec ? Number(billsec) : 0;

  if (answered && !call.connected_at) {
    await db.query('UPDATE call_logs SET connected_at = NOW() WHERE id = $1', [call.id]);
  }

  const isRinging = diagnostics.sipStatus === 180 || diagnostics.sipStatus === 183;
  return {
    status: answered ? 'in_call' : isRinging ? 'ringing' : 'queued',
    sipStatus: diagnostics.sipStatus ?? call.sip_status ?? null,
    sipReason: diagnostics.sipReason ?? call.sip_reason ?? null,
    hangupCause: diagnostics.hangupCause ?? call.hangup_cause ?? null,
    recordingPath: call.recording_path,
    durationSeconds
  };
};

const mute = async ({ uuid, userId }) => {
  await findCallByUuid(uuid, userId);
  await freeswitch.muteCall(uuid);
};

const unmute = async ({ uuid, userId }) => {
  await findCallByUuid(uuid, userId);
  await freeswitch.unmuteCall(uuid);
};

const hangup = async ({ uuid, userId }) => {
  const call = await findCallByUuid(uuid, userId);
  await freeswitch.hangupCall(uuid);
  await updateCallCompletion(call.id, call.duration_seconds);
};

module.exports = {
  getStatus,
  mute,
  unmute,
  hangup
};
