const express = require('express');
const Joi = require('joi');
const authService = require('../services/authService');

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

module.exports = router;
