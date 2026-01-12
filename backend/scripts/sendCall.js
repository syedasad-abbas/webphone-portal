const db = require('../src/db');
const callService = require('../src/services/callService');
const config = require('../src/config');

const FROM_NUMBER = process.env.CALL_FROM || '+18008136570';
const TO_NUMBER = process.env.CALL_TO || '+14099168086';
const CARRIER_DOMAIN = process.env.CALL_CARRIER_DOMAIN || 'sipconvo.voslogic.com';
const CARRIER_PORT = process.env.CALL_CARRIER_PORT || config.defaults.carrierPort;
const CARRIER_TRANSPORT = process.env.CALL_CARRIER_TRANSPORT || config.defaults.carrierTransport;
const CARRIER_NAME = process.env.CALL_CARRIER_NAME || config.defaults.carrierName;
const PREFIX = process.env.CALL_PREFIX || '';
const USER_EMAIL = process.env.CALL_USER_EMAIL || config.defaults.adminEmail;

const upsertCarrier = async () => {
  const result = await db.query(
    `INSERT INTO carriers (name, default_caller_id, caller_id_required, sip_domain, sip_port, transport)
     VALUES ($1, $2, true, $3, $4, $5)
     ON CONFLICT (name)
     DO UPDATE SET default_caller_id = EXCLUDED.default_caller_id,
                   sip_domain = EXCLUDED.sip_domain,
                   sip_port = EXCLUDED.sip_port,
                   transport = EXCLUDED.transport
     RETURNING id`,
    [CARRIER_NAME, FROM_NUMBER, CARRIER_DOMAIN, CARRIER_PORT, CARRIER_TRANSPORT]
  );
  return result.rows[0].id;
};

const ensurePrefix = async (carrierId) => {
  if (!PREFIX) {
    return;
  }
  const existing = await db.query(
    `SELECT id
       FROM carrier_prefixes
      WHERE carrier_id = $1
        AND prefix = $2`,
    [carrierId, PREFIX]
  );
  if (existing.rowCount > 0) {
    return;
  }
  await db.query(
    `INSERT INTO carrier_prefixes (carrier_id, prefix, caller_id)
     VALUES ($1, $2, $3)`,
    [carrierId, PREFIX, FROM_NUMBER]
  );
};

const ensureUserCarrier = async (carrierId) => {
  const result = await db.query(
    `UPDATE users
        SET carrier_id = $1
      WHERE email = $2
      RETURNING id`,
    [carrierId, USER_EMAIL]
  );
  if (result.rowCount === 0) {
    throw new Error(`User not found for email ${USER_EMAIL}`);
  }
  return result.rows[0].id;
};

const run = async () => {
  try {
    const carrierId = await upsertCarrier();
    await ensurePrefix(carrierId);
    const userId = await ensureUserCarrier(carrierId);

    const response = await callService.originate({
      user: { id: userId },
      destination: TO_NUMBER,
      callerId: FROM_NUMBER
    });

    console.log('Call queued:', response);
  } finally {
    await db.pool.end();
  }
};

run().catch((err) => {
  console.error('Failed to place call:', err.message);
  process.exit(1);
});
