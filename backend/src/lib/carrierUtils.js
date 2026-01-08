const normalizeGatewayName = (carrier) => {
  if (!carrier) {
    return null;
  }
  const source = carrier.id || carrier.name;
  if (!source) {
    return null;
  }
  return source.toLowerCase().replace(/[^a-z0-9_-]+/g, '_');
};

const formatSipEndpoint = (carrier) => {
  if (!carrier || !carrier.sip_domain) {
    return null;
  }
  return carrier.sip_port ? `${carrier.sip_domain}:${carrier.sip_port}` : carrier.sip_domain;
};

module.exports = {
  normalizeGatewayName,
  formatSipEndpoint
};
