const express = require('express');
const Joi = require('joi');
const authService = require('../services/authService');
const passwordResetService = require('../services/passwordResetService');
const config = require('../config');

const router = express.Router();

router.post('/login', async (req, res) => {
  const schema = Joi.object({
    email: Joi.string().email({ tlds: { allow: false } }).required(),
    password: Joi.string().required()
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  try {
    const response = await authService.authenticate(value.email, value.password, 'user');
    return res.json(response);
  } catch (err) {
    return res.status(401).json({ message: err.message });
  }
});

router.post('/forgot-password', async (req, res) => {
  const schema = Joi.object({
    email: Joi.string().email({ tlds: { allow: false } }).required(),
    role: Joi.string().valid('user', 'admin').default('user')
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  const internalToken = config.passwordReset.internalToken;
  const requestToken = req.get('x-internal-token');
  if (internalToken && internalToken !== requestToken) {
    return res.json({ message: 'If the account exists, a code will be sent.' });
  }

  try {
    const otp = await passwordResetService.createOtp(value.email, value.role);
    if (!otp) {
      return res.json({ message: 'If the account exists, a code will be sent.' });
    }
    return res.json({
      message: 'OTP created.',
      code: otp.code,
      expiresAt: otp.expiresAt
    });
  } catch (err) {
    return res.status(500).json({ message: 'Unable to create OTP.' });
  }
});

router.post('/reset-password', async (req, res) => {
  const schema = Joi.object({
    email: Joi.string().email({ tlds: { allow: false } }).required(),
    role: Joi.string().valid('user', 'admin').default('user'),
    code: Joi.string().length(6).required(),
    password: Joi.string().min(6).required()
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  try {
    await passwordResetService.resetPassword({
      email: value.email,
      code: value.code,
      newPassword: value.password,
      role: value.role
    });
    return res.json({ message: 'Password updated.' });
  } catch (err) {
    return res.status(400).json({ message: err.message || 'Unable to reset password.' });
  }
});

module.exports = router;
