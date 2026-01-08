const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const db = require('../db');
const config = require('../config');

const generateToken = (user) =>
  jwt.sign(
    {
      id: user.id,
      email: user.email,
      role: user.role,
      groupId: user.group_id,
      carrierId: user.carrier_id
    },
    config.jwtSecret,
    { expiresIn: '12h' }
  );

const authenticate = async (email, password, role) => {
  const result = await db.query('SELECT * FROM users WHERE email = $1 AND role = $2', [email, role]);
  if (result.rowCount === 0) {
    throw new Error('Invalid credentials');
  }
  const user = result.rows[0];
  const match = await bcrypt.compare(password, user.password_hash);
  if (!match) {
    throw new Error('Invalid credentials');
  }

  const token = generateToken(user);
  return { token, user };
};

module.exports = {
  authenticate
};
