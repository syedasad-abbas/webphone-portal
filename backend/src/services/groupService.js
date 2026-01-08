const db = require('../db');

const listGroups = async () => {
  const result = await db.query('SELECT id, name, permissions FROM groups ORDER BY name ASC');
  return result.rows;
};

const createGroup = async ({ name, permissions }) => {
  const insert = await db.query(
    'INSERT INTO groups (name, permissions) VALUES ($1, $2::jsonb) RETURNING id, name, permissions',
    [name, JSON.stringify(permissions || [])]
  );
  return insert.rows[0];
};

module.exports = {
  listGroups,
  createGroup
};
