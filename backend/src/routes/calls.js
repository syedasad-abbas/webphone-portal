const express = require('express');
const Joi = require('joi');
const { authenticate } = require('../middleware/auth');
const callService = require('../services/callService');
const callControlService = require('../services/callControlService');

const router = express.Router();

router.post('/', authenticate('user'), async (req, res) => {
  const schema = Joi.object({
    destination: Joi.string().required(),
    callerId: Joi.string().optional()
  });

  const { error, value } = schema.validate(req.body);
  if (error) {
    return res.status(400).json({ message: error.message });
  }

  try {
    const response = await callService.originate({
      user: req.user,
      destination: value.destination,
      callerId: value.callerId
    });

    return res.json({
      status: response.status,
      callUuid: response.callUuid
    });
  } catch (err) {
    return res.status(500).json({ message: err.message });
  }
});

router.get('/:uuid', authenticate('user'), async (req, res) => {
  try {
    const status = await callControlService.getStatus({
      uuid: req.params.uuid,
      userId: req.user.id
    });
    return res.json(status);
  } catch (err) {
    return res.status(404).json({ message: err.message });
  }
});

router.post('/:uuid/mute', authenticate('user'), async (req, res) => {
  try {
    await callControlService.mute({ uuid: req.params.uuid, userId: req.user.id });
    return res.json({ status: 'muted' });
  } catch (err) {
    return res.status(400).json({ message: err.message });
  }
});

router.post('/:uuid/unmute', authenticate('user'), async (req, res) => {
  try {
    await callControlService.unmute({ uuid: req.params.uuid, userId: req.user.id });
    return res.json({ status: 'unmuted' });
  } catch (err) {
    return res.status(400).json({ message: err.message });
  }
});

router.post('/:uuid/hangup', authenticate('user'), async (req, res) => {
  try {
    await callControlService.hangup({ uuid: req.params.uuid, userId: req.user.id });
    return res.json({ status: 'ended' });
  } catch (err) {
    return res.status(400).json({ message: err.message });
  }
});

module.exports = router;
