const fs = require('fs/promises');
const path = require('path');
const config = require('../config');
const freeswitch = require('./freeswitch');
const { normalizeGatewayName, formatSipEndpoint } = require('./carrierUtils');

const gatewayDir = config.freeswitch.gatewayConfigPath;
const sipProfile = config.freeswitch.profile || 'external';

const ensureGatewayDir = async () => {
  if (!gatewayDir) {
    return false;
  }
  await fs.mkdir(gatewayDir, { recursive: true });
  return true;
};

const escapeXml = (value) =>
  value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

const getGatewayFilePath = (gatewayName) => {
  if (!gatewayDir || !gatewayName) {
    return null;
  }
  return path.join(gatewayDir, `${gatewayName}.xml`);
};

const buildGatewayXml = (carrier, gatewayName) => {
  const endpoint = formatSipEndpoint(carrier);
  const transport = (carrier.transport || 'udp').toLowerCase();
  const shouldRegister = Boolean(carrier.registration_required);
  const params = [
    { name: 'username', value: carrier.registration_username },
    { name: 'password', value: carrier.registration_password },
    { name: 'from-user', value: carrier.registration_username },
    { name: 'from-domain', value: carrier.sip_domain },
    { name: 'extension', value: carrier.registration_username },
    { name: 'proxy', value: endpoint },
    { name: 'outbound-proxy', value: carrier.outbound_proxy },
    { name: 'register-proxy', value: shouldRegister ? endpoint : null },
    { name: 'register-transport', value: shouldRegister ? transport : null },
    { name: 'register', value: shouldRegister ? 'true' : 'false' },
    { name: 'expire-seconds', value: shouldRegister ? '3600' : null },
    { name: 'retry-seconds', value: shouldRegister ? '30' : null },
    { name: 'ping', value: shouldRegister ? '30' : null }
  ].filter((param) => param.value);

  const paramXml = params
    .map(
      (param) =>
        `    <param name="${param.name}" value="${escapeXml(param.value.toString())}"/>`
    )
    .join('\n');

  return `<include>
  <gateway name="${gatewayName}">
${paramXml}
  </gateway>
</include>
`;
};

const removeGatewayFile = async (gatewayName) => {
  const filePath = getGatewayFilePath(gatewayName);
  if (!filePath) {
    return;
  }
  try {
    await fs.unlink(filePath);
  } catch (err) {
    if (err.code !== 'ENOENT') {
      throw err;
    }
  }
};

const reloadProfile = async () => {
  try {
    await freeswitch.reloadXml();
    await freeswitch.rescanProfile(sipProfile);
  } catch (err) {
    // Non-fatal: FreeSWITCH connectivity issues will surface elsewhere.
  }
};

const killGateway = async (gatewayName) => {
  if (!gatewayName) {
    return;
  }
  try {
    await freeswitch.killGateway(gatewayName, sipProfile);
  } catch (err) {
    // Ignore; gateway might not exist yet.
  }
};

const syncGateway = async (carrier) => {
  if (!gatewayDir) {
    return;
  }
  const gatewayName = normalizeGatewayName(carrier);
  if (!gatewayName) {
    return;
  }

  const shouldKeepGateway = Boolean(carrier.registration_required || carrier.outbound_proxy);
  if (!shouldKeepGateway) {
    await removeGatewayFile(gatewayName);
    await killGateway(gatewayName);
    await reloadProfile();
    return;
  }

  if (carrier.registration_required) {
    if (!carrier.registration_username || !carrier.registration_password) {
      throw new Error('Registration credentials are required when enabling registration.');
    }
  }
  if (!carrier.sip_domain) {
    throw new Error('A SIP domain or IP is required for carrier registration.');
  }
  const endpoint = formatSipEndpoint(carrier);
  if (!endpoint) {
    throw new Error('A valid SIP endpoint is required for carrier registration.');
  }

  const created = await ensureGatewayDir();
  if (!created) {
    return;
  }

  const filePath = getGatewayFilePath(gatewayName);
  if (!filePath) {
    return;
  }
  const xml = buildGatewayXml(carrier, gatewayName);
  await fs.writeFile(filePath, xml, 'utf8');
  await reloadProfile();
};

const removeGateway = async (carrier) => {
  if (!gatewayDir || !carrier) {
    return;
  }
  const gatewayName = normalizeGatewayName(carrier);
  if (!gatewayName) {
    return;
  }
  await removeGatewayFile(gatewayName);
  await killGateway(gatewayName);
  await reloadProfile();
};

module.exports = {
  syncGateway,
  removeGateway
};
