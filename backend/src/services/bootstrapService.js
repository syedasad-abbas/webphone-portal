const bcrypt = require('bcryptjs');
const db = require('../db');
const config = require('../config');

const ensureDefaults = async () => {
  const client = await db.pool.connect();
  try {
    await client.query('BEGIN');

    const groupResult = await client.query('SELECT id FROM groups WHERE name = $1', [config.defaults.groupName]);
    let groupId = groupResult.rows[0]?.id;
    if (!groupId) {
      const insertGroup = await client.query(
        'INSERT INTO groups (name, permissions) VALUES ($1, $2::jsonb) RETURNING id',
        [config.defaults.groupName, JSON.stringify(config.defaults.groupPermissions)]
      );
      groupId = insertGroup.rows[0].id;
    }

    const carrierResult = await client.query('SELECT id FROM carriers WHERE name = $1', [config.defaults.carrierName]);
    let carrierId = carrierResult.rows[0]?.id;
    if (!carrierId) {
      const insertCarrier = await client.query(
        'INSERT INTO carriers (name, default_caller_id, sip_domain, sip_port, transport) VALUES ($1, $2, $3, $4, $5) RETURNING id',
        [
          config.defaults.carrierName,
          config.defaults.carrierCallerId,
          config.defaults.carrierDomain,
          config.defaults.carrierPort,
          config.defaults.carrierTransport
        ]
      );
      carrierId = insertCarrier.rows[0].id;
    } else {
      await client.query(
        `UPDATE carriers
            SET sip_domain = COALESCE(sip_domain, $2),
                sip_port = COALESCE(sip_port, $3),
                transport = COALESCE(transport, $4)
          WHERE id = $1`,
        [carrierId, config.defaults.carrierDomain, config.defaults.carrierPort, config.defaults.carrierTransport]
      );
    }

    const passwordHash = await bcrypt.hash(config.defaults.adminPassword, 10);
    const adminByEmail = await client.query('SELECT id FROM users WHERE email = $1 AND role = $2', [
      config.defaults.adminEmail,
      'admin'
    ]);

    if (adminByEmail.rowCount === 0) {
      const existingAdmin = await client.query('SELECT id FROM users WHERE role = $1 ORDER BY created_at ASC LIMIT 1', [
        'admin'
      ]);

      if (existingAdmin.rowCount === 0) {
        await client.query(
          `INSERT INTO users (full_name, email, password_hash, role, group_id, carrier_id, recording_enabled)
           VALUES ($1, $2, $3, 'admin', $4, $5, true)`,
          ['Default Admin', config.defaults.adminEmail, passwordHash, groupId, carrierId]
        );
      } else {
        await client.query(
          `UPDATE users
             SET email = $1,
                 password_hash = $2,
                 group_id = $3,
                 carrier_id = $4,
                 recording_enabled = true
           WHERE id = $5`,
          [config.defaults.adminEmail, passwordHash, groupId, carrierId, existingAdmin.rows[0].id]
        );
      }
    } else {
      await client.query(
        `UPDATE users
           SET password_hash = $1,
               group_id = $2,
               carrier_id = $3,
               recording_enabled = true
         WHERE id = $4`,
        [passwordHash, groupId, carrierId, adminByEmail.rows[0].id]
      );
    }

    await client.query('COMMIT');
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  } finally {
    client.release();
  }
};

module.exports = {
  ensureDefaults
};
