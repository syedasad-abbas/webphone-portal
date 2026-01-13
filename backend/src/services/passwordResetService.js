const bcrypt = require('bcryptjs');
const db = require('../db');
const config = require('../config');

const generateCode = () => {
  const value = Math.floor(Math.random() * 1000000);
  return value.toString().padStart(6, '0');
};

const findUserByEmail = async (email, role) => {
  const result = await db.query(
    'SELECT id, email, role FROM users WHERE email = $1 AND role = $2',
    [email, role]
  );
  return result.rows[0] || null;
};

const createOtp = async (email, role = 'user') => {
  const user = await findUserByEmail(email, role);
  if (!user) {
    return null;
  }

  await db.query(
    'DELETE FROM password_reset_otps WHERE user_id = $1 AND consumed_at IS NULL',
    [user.id]
  );

  const code = generateCode();
  const codeHash = await bcrypt.hash(code, 10);
  const ttlMinutes = config.passwordReset.otpTtlMinutes;
  const expiresAt = new Date(Date.now() + ttlMinutes * 60 * 1000);

  await db.query(
    `INSERT INTO password_reset_otps (user_id, code_hash, expires_at)
     VALUES ($1, $2, $3)`,
    [user.id, codeHash, expiresAt]
  );

  return { code, expiresAt };
};

const resetPassword = async ({ email, code, newPassword, role = 'user' }) => {
  const user = await findUserByEmail(email, role);
  if (!user) {
    throw new Error('Invalid or expired code.');
  }

  const otpResult = await db.query(
    `SELECT id, code_hash, attempt_count
     FROM password_reset_otps
     WHERE user_id = $1
       AND consumed_at IS NULL
       AND expires_at > NOW()
     ORDER BY created_at DESC
     LIMIT 1`,
    [user.id]
  );

  const otp = otpResult.rows[0];
  if (!otp) {
    throw new Error('Invalid or expired code.');
  }

  const matches = await bcrypt.compare(code, otp.code_hash);
  if (!matches) {
    const nextAttempts = (otp.attempt_count || 0) + 1;
    const maxAttempts = config.passwordReset.maxAttempts;
    const consumedAt = nextAttempts >= maxAttempts ? new Date() : null;
    await db.query(
      `UPDATE password_reset_otps
       SET attempt_count = $2,
           consumed_at = COALESCE($3, consumed_at)
       WHERE id = $1`,
      [otp.id, nextAttempts, consumedAt]
    );
    throw new Error('Invalid or expired code.');
  }

  const passwordHash = await bcrypt.hash(newPassword, 10);
  await db.query('UPDATE users SET password_hash = $1 WHERE id = $2', [passwordHash, user.id]);
  await db.query('UPDATE password_reset_otps SET consumed_at = NOW() WHERE id = $1', [otp.id]);
};

module.exports = {
  createOtp,
  resetPassword
};
