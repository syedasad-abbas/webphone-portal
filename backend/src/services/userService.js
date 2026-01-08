const bcrypt = require('bcryptjs');
const db = require('../db');
const config = require('../config');

const resolveGroupId = async (groupId) => {
  if (groupId) {
    return groupId;
  }
  const result = await db.query('SELECT id FROM groups WHERE name = $1', [config.defaults.groupName]);
  return result.rows[0]?.id || null;
};

const resolveCarrierId = async (carrierId) => {
  if (carrierId) {
    return carrierId;
  }
  const result = await db.query('SELECT id FROM carriers WHERE name = $1', [config.defaults.carrierName]);
  return result.rows[0]?.id || null;
};

const createUser = async ({ fullName, email, password, groupId, carrierId, permissions }) => {
  const passwordHash = await bcrypt.hash(password, 10);
  const resolvedGroupId = await resolveGroupId(groupId);
  const resolvedCarrierId = await resolveCarrierId(carrierId);

  const insert = await db.query(
    `INSERT INTO users (full_name, email, password_hash, role, group_id, carrier_id, recording_enabled, permissions)
     VALUES ($1, $2, $3, 'user', $4, $5, true, $6::jsonb)
     RETURNING id, full_name, email, group_id, carrier_id, recording_enabled`,
    [fullName, email, passwordHash, resolvedGroupId, resolvedCarrierId, JSON.stringify(permissions || [])]
  );

  return insert.rows[0];
};

const listUsers = async () => {
  const result = await db.query(
    `SELECT users.id,
            users.full_name,
            users.email,
            users.recording_enabled,
            groups.name AS group_name,
            carriers.name AS carrier_name
     FROM users
     LEFT JOIN groups ON groups.id = users.group_id
     LEFT JOIN carriers ON carriers.id = users.carrier_id
     WHERE users.role = 'user'
     ORDER BY users.created_at DESC`
  );
  return result.rows;
};

const getUserById = async (id) => {
  const result = await db.query('SELECT * FROM users WHERE id = $1', [id]);
  return result.rows[0];
};

const updateUser = async (id, { fullName, email, password, groupId, carrierId, permissions, recordingEnabled }) => {
  const existing = await getUserById(id);
  if (!existing || existing.role !== 'user') {
    throw new Error('User not found');
  }

  const resolvedGroupId = await resolveGroupId(groupId);
  const resolvedCarrierId = await resolveCarrierId(carrierId);
  const passwordHash = password ? await bcrypt.hash(password, 10) : null;

  const result = await db.query(
    `UPDATE users
     SET full_name = $1,
         email = $2,
         group_id = $3,
         carrier_id = $4,
         permissions = $5::jsonb,
         recording_enabled = COALESCE($6, recording_enabled),
         password_hash = COALESCE($7, password_hash)
     WHERE id = $8 AND role = 'user'
     RETURNING id, full_name, email, group_id, carrier_id, recording_enabled`,
    [
      fullName || existing.full_name,
      email || existing.email,
      resolvedGroupId,
      resolvedCarrierId,
      JSON.stringify(permissions !== undefined ? permissions : existing.permissions || []),
      recordingEnabled,
      passwordHash,
      id
    ]
  );

  if (result.rowCount === 0) {
    throw new Error('Unable to update user');
  }

  return result.rows[0];
};

const deleteUser = async (id) => {
  await db.query('DELETE FROM call_logs WHERE user_id = $1', [id]);
  const result = await db.query("DELETE FROM users WHERE id = $1 AND role = 'user'", [id]);
  if (result.rowCount === 0) {
    throw new Error('User not found');
  }
};

module.exports = {
  createUser,
  listUsers,
  updateUser,
  deleteUser,
  getUserById
};
