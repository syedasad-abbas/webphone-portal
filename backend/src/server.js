const express = require('express');
const cors = require('cors');
const morgan = require('morgan');
const config = require('./config');
const { ensureDefaults } = require('./services/bootstrapService');
const adminRoutes = require('./routes/admin');
const authRoutes = require('./routes/auth');
const callRoutes = require('./routes/calls');

const app = express();

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(morgan('dev'));

app.get('/health', (_req, res) => res.json({ status: 'ok' }));
app.use('/admin', adminRoutes);
app.use('/auth', authRoutes);
app.use('/calls', callRoutes);

const start = async () => {
  await ensureDefaults();
  app.listen(config.port, () => {
    console.log(`Backend listening on port ${config.port}`);
  });
};

start().catch((err) => {
  console.error('Failed to start backend', err);
  process.exit(1);
});
