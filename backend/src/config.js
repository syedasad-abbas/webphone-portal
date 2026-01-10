require('dotenv').config();

const parseJSON = (value, fallback) => {
  try {
    return JSON.parse(value);
  } catch (err) {
    return fallback;
  }
};

const optionalEnv = (value, fallback = null) => {
  if (typeof value !== 'string') {
    return fallback;
  }
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : fallback;
};

const toInt = (value, fallback = null) => {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }
  const parsed = parseInt(value, 10);
  return Number.isNaN(parsed) ? fallback : parsed;
};

const sanitizeTransport = (value, fallback = 'udp') => {
  const normalized = (value || '').toString().trim().toLowerCase();
  return ['udp', 'tcp', 'tls'].includes(normalized) ? normalized : fallback;
};

module.exports = {
  port: process.env.PORT || 4000,
  db: {
    host: process.env.DB_HOST || 'db',
    port: process.env.DB_PORT || 5432,
    user: process.env.DB_USER || 'webphone',
    password: process.env.DB_PASSWORD || 'supersecret',
    database: process.env.DB_NAME || 'webphone'
  },
  jwtSecret: process.env.JWT_SECRET || 'change_me',
  freeswitch: {
    host: process.env.FREESWITCH_HOST || 'freeswitch',
    port: process.env.FREESWITCH_PORT || 8021,
    password: process.env.FREESWITCH_PASSWORD || 'ClueCon',
    recordingsPath: process.env.FREESWITCH_RECORDINGS_PATH || '/var/recordings',
    externalSipIp: optionalEnv(process.env.FREESWITCH_EXTERNAL_SIP_IP, null),
    profile: optionalEnv(process.env.FREESWITCH_SIP_PROFILE, 'external') || 'external',
    gatewayConfigPath: optionalEnv(process.env.FREESWITCH_GATEWAY_PATH, null)
  },
  defaults: {
    adminEmail: process.env.DEFAULT_ADMIN_EMAIL || 'admin@webphone.local',
    adminPassword: process.env.DEFAULT_ADMIN_PASSWORD || 'AdminPass123!',
    groupName: process.env.DEFAULT_GROUP_NAME || 'Standard User',
    groupPermissions: parseJSON(process.env.DEFAULT_GROUP_PERMISSIONS || '["dial"]', ['dial']),
    carrierName: process.env.DEFAULT_CARRIER_NAME || 'Default Carrier',
    carrierCallerId: process.env.DEFAULT_CARRIER_CALLER_ID || '1000',
    carrierDomain: optionalEnv(process.env.DEFAULT_CARRIER_DOMAIN, '127.0.0.1'),
    carrierPort: toInt(process.env.DEFAULT_CARRIER_PORT, 5062),
    carrierTransport: sanitizeTransport(process.env.DEFAULT_CARRIER_TRANSPORT, 'udp')
  }
};
