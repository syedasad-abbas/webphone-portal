const express = require('express');
const Joi = require('joi');
const authService = require('../services/authService');
const userService = require('../services/userService');
const groupService = require('../services/groupService');
const carrierService = require('../services/carrierService');
const { authenticate } = require('../middleware/auth');
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
    const response = await authService.authenticate(value.email, value.password, 'admin');
    return res.json(response);
  } catch (err) {
    return res.status(401).json({ message: err.message });
  }
});

router.get('/users', authenticate('admin'), async (_req, res) => {
  const users = await userService.listUsers();
  return res.json(users);
});

router.get('/users/:userId', authenticate('admin'), async (req, res) => {
  try {
    const user = await userService.getUserById(req.params.userId);
    if (!user || user.role !== 'user') {
      return res.status(404).json({ message: 'User not found' });
    }
    return res.json(user);
  } catch (err) {
    return res.status(404).json({ message: 'User not found' });
  }
});

router.post('/users', authenticate('admin'), async (req, res) => {
  const schema = Joi.object({
    fullName: Joi.string().required(),
    email: Joi.string().email({ tlds: { allow: false } }).required(),
    password: Joi.string().min(6).required(),
    groupId: Joi.string().uuid().allow(null, '').optional(),
    carrierId: Joi.string().uuid().allow(null, '').optional(),
    permissions: Joi.array().items(Joi.string()).optional()
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  try {
    const user = await userService.createUser(value);
    return res.status(201).json(user);
  } catch (err) {
    return res.status(500).json({ message: err.message });
  }
});

router.put('/users/:userId', authenticate('admin'), async (req, res) => {
  const schema = Joi.object({
    fullName: Joi.string().optional(),
    email: Joi.string().email({ tlds: { allow: false } }).optional(),
    password: Joi.string().min(6).allow('', null),
    groupId: Joi.string().uuid().allow(null, '').optional(),
    carrierId: Joi.string().uuid().allow(null, '').optional(),
    permissions: Joi.array().items(Joi.string()).optional(),
    recordingEnabled: Joi.boolean().optional()
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  try {
    const user = await userService.updateUser(req.params.userId, {
      ...value,
      password: value.password || undefined
    });
    return res.json(user);
  } catch (err) {
    return res.status(400).json({ message: err.message });
  }
});

router.delete('/users/:userId', authenticate('admin'), async (req, res) => {
  try {
    await userService.deleteUser(req.params.userId);
    return res.status(204).send();
  } catch (err) {
    return res.status(400).json({ message: err.message });
  }
});

router.get('/groups', authenticate('admin'), async (_req, res) => {
  const groups = await groupService.listGroups();
  return res.json(groups);
});

router.post('/groups', authenticate('admin'), async (req, res) => {
  const schema = Joi.object({
    name: Joi.string().required(),
    permissions: Joi.array().items(Joi.string()).default([])
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  const group = await groupService.createGroup(value);
  return res.status(201).json(group);
});

router.get('/carriers', authenticate('admin'), async (_req, res) => {
  const carriers = await carrierService.listCarriers();
  return res.json(carriers);
});

router.get('/carriers/:carrierId', authenticate('admin'), async (req, res) => {
  const carrier = await carrierService.getCarrierById(req.params.carrierId);
  if (!carrier) {
    return res.status(404).json({ message: 'Carrier not found' });
  }
  return res.json(carrier);
});

router.post('/carriers', authenticate('admin'), async (req, res) => {
  const schema = Joi.object({
    name: Joi.string().required(),
    callerId: Joi.string().allow('', null),
    callerIdRequired: Joi.boolean().default(false),
    transport: Joi.string().valid('udp', 'tcp', 'tls').default('udp'),
    sipDomain: Joi.string().required(),
    sipPort: Joi.number().integer().min(1).max(65535).required(),
    outboundProxy: Joi.string().allow('', null),
    registrationRequired: Joi.boolean().default(false),
    registrationUsername: Joi.string().allow('', null),
    registrationPassword: Joi.string().allow('', null),
    prefix: Joi.string().allow('', null),
    prefixCallerId: Joi.string().allow('', null)
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  try {
    let carrier = await carrierService.createCarrier(value);
    if (value.prefix) {
      const prefixCaller =
        value.prefixCallerId || value.callerId || config.defaults.carrierCallerId || null;
      await carrierService.addPrefix({
        carrierId: carrier.id,
        prefix: value.prefix,
        callerId: prefixCaller
      });
      carrier = await carrierService.getCarrierById(carrier.id);
    }
    return res.status(201).json(carrier);
  } catch (err) {
    return res.status(400).json({ message: err.message || 'Unable to create carrier' });
  }
});

router.put('/carriers/:carrierId', authenticate('admin'), async (req, res) => {
  const schema = Joi.object({
    name: Joi.string().optional(),
    callerId: Joi.string().allow('', null).optional(),
    callerIdRequired: Joi.boolean().optional(),
    transport: Joi.string().valid('udp', 'tcp', 'tls').optional(),
    sipDomain: Joi.string().allow('', null).optional(),
    sipPort: Joi.number().integer().min(1).max(65535).allow(null).optional(),
    outboundProxy: Joi.string().allow('', null).optional(),
    registrationRequired: Joi.boolean().optional(),
    registrationUsername: Joi.string().allow('', null).optional(),
    registrationPassword: Joi.string().allow('', null).optional(),
    prefix: Joi.string().allow('', null),
    prefixCallerId: Joi.string().allow('', null)
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  try {
    let carrier = await carrierService.updateCarrier(req.params.carrierId, value);
    if (value.prefix) {
      const prefixCaller =
        value.prefixCallerId || value.callerId || carrier.default_caller_id || config.defaults.carrierCallerId || null;
      await carrierService.addPrefix({
        carrierId: carrier.id,
        prefix: value.prefix,
        callerId: prefixCaller
      });
      carrier = await carrierService.getCarrierById(carrier.id);
    }
    return res.json(carrier);
  } catch (err) {
    return res.status(400).json({ message: err.message || 'Unable to update carrier' });
  }
});

router.delete('/carriers/:carrierId', authenticate('admin'), async (req, res) => {
  try {
    await carrierService.deleteCarrier(req.params.carrierId);
    return res.status(204).send();
  } catch (err) {
    if (err && err.code === 'CARRIER_IN_USE') {
      return res.status(409).json({ message: err.message });
    }
    return res.status(400).json({ message: err.message || 'Unable to delete carrier.' });
  }
});

router.post('/carriers/:carrierId/prefixes', authenticate('admin'), async (req, res) => {
  const schema = Joi.object({
    prefix: Joi.string().required(),
    callerId: Joi.string().required()
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  const entry = await carrierService.addPrefix({
    carrierId: req.params.carrierId,
    prefix: value.prefix,
    callerId: value.callerId
  });
  return res.status(201).json(entry);
});

module.exports = router;
