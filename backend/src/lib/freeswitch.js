const net = require('net');
const config = require('../config');

const sendCommand = (command) =>
  new Promise((resolve, reject) => {
    const client = new net.Socket();
    let buffer = '';
    let authed = false;

    const processFrames = () => {
      while (true) {
        const headerEnd = buffer.indexOf('\n\n');
        if (headerEnd === -1) {
          return;
        }
        const header = buffer.slice(0, headerEnd);
        const lengthMatch = header.match(/Content-Length:\s*(\d+)/i);
        const bodyLength = lengthMatch ? parseInt(lengthMatch[1], 10) : 0;
        const frameLength = headerEnd + 2 + bodyLength;
        if (buffer.length < frameLength) {
          return;
        }
        const body = buffer.slice(headerEnd + 2, frameLength);
        buffer = buffer.slice(frameLength);

        if (header.includes('Content-Type: auth/request')) {
          client.write(`auth ${config.freeswitch.password}\n\n`);
          continue;
        }

        if (header.includes('Content-Type: command/reply')) {
          const replyLine = header.split('\n').find((line) => line.startsWith('Reply-Text'));
          const ok = replyLine && replyLine.includes('+OK');

          if (!authed) {
            if (ok) {
              authed = true;
              client.write(`${command}\n\n`);
            } else {
              client.end();
              reject(new Error(replyLine || 'FreeSWITCH auth failed'));
            }
            continue;
          }

          client.end();
          if (ok) {
            resolve(body.length > 0 ? body.toString().trim() : replyLine);
          } else {
            reject(new Error(replyLine || body.toString().trim() || 'Unknown FreeSWITCH error'));
          }
          return;
        }

        if (header.includes('Content-Type: text/disconnect-notice')) {
          client.end();
          reject(new Error('FreeSWITCH disconnected'));
          return;
        }
      }
    };

    client.connect(config.freeswitch.port, config.freeswitch.host, () => {});
    client.on('data', (data) => {
      buffer += data.toString();
      processFrames();
    });
    client.on('error', (err) => {
      reject(err);
    });
  });

const originateCall = async ({ destination, callerId, gateway, recordingPath, endpoint, variables = [] }) => {
  const vars = ['originate_timeout=30', 'ignore_early_media=true'];

  if (callerId) {
    vars.unshift(`origination_caller_id_number=${callerId}`);
  }

  if (recordingPath) {
    vars.push(`execute_on_answer=record_session::${recordingPath}`);
    vars.push(`recording_path=${recordingPath}`);
  }

  if (Array.isArray(variables) && variables.length > 0) {
    vars.push(...variables);
  }

  let dialString = endpoint;
  if (!dialString) {
    if (!gateway || !destination) {
      throw new Error('Gateway and destination are required when endpoint is not provided');
    }
    dialString = `sofia/gateway/${gateway}/${destination}`;
  }

  const originateString = `bgapi originate {${vars.join(',')}}${dialString} &park`;
  const response = await sendCommand(originateString);
  const jobUuid = (response.match(/Job-UUID:\s*([0-9a-f-]+)/i) || [])[1];
  return { response, jobUuid };
};

const callExists = async (uuid) => {
  try {
    const response = await sendCommand(`bgapi uuid_exists ${uuid}`);
    return response.includes('+OK');
  } catch (err) {
    return false;
  }
};

const parseReplyValue = (response) => {
  const match = response.match(/Reply-Text:\s+\+OK\s*(.*)/i);
  if (match && typeof match[1] === 'string') {
    return match[1].trim();
  }
  return null;
};

const getChannelVar = async (uuid, variable) => {
  try {
    const response = await sendCommand(`bgapi uuid_getvar ${uuid} ${variable}`);
    return parseReplyValue(response);
  } catch (err) {
    return null;
  }
};

const muteCall = async (uuid) => sendCommand(`bgapi uuid_audio ${uuid} start read mute`);
const unmuteCall = async (uuid) => sendCommand(`bgapi uuid_audio ${uuid} stop read mute`);
const hangupCall = async (uuid) => sendCommand(`bgapi uuid_kill ${uuid}`);

const sendApiCommand = (command) =>
  new Promise((resolve, reject) => {
    const client = new net.Socket();
    let buffer = '';
    let authed = false;

    const processFrames = () => {
      while (true) {
        const headerEnd = buffer.indexOf('\n\n');
        if (headerEnd === -1) {
          return;
        }
        const header = buffer.slice(0, headerEnd);
        const lengthMatch = header.match(/Content-Length:\s*(\d+)/i);
        const bodyLength = lengthMatch ? parseInt(lengthMatch[1], 10) : 0;
        const totalLength = headerEnd + 2 + bodyLength;
        if (buffer.length < totalLength) {
          return;
        }
        const body = buffer.slice(headerEnd + 2, totalLength);
        buffer = buffer.slice(totalLength);

        if (header.includes('Content-Type: auth/request')) {
          client.write(`auth ${config.freeswitch.password}\n\n`);
          continue;
        }

        if (header.includes('Content-Type: command/reply')) {
          const replyLine = header.split('\n').find((line) => line.startsWith('Reply-Text'));
          const ok = replyLine && replyLine.includes('+OK');
          if (!authed) {
            if (ok) {
              authed = true;
              client.write(`api ${command}\n\n`);
            } else {
              client.end();
              reject(new Error(replyLine || 'FreeSWITCH auth failed'));
            }
            continue;
          }

          if (!ok) {
            client.end();
            reject(new Error(replyLine || 'FreeSWITCH error'));
            return;
          }
          continue;
        }

        if (header.includes('Content-Type: api/response')) {
          client.end();
          resolve(body.toString().trim());
          return;
        }

        if (header.includes('Content-Type: text/disconnect-notice')) {
          client.end();
          reject(new Error('FreeSWITCH disconnected'));
          return;
        }
      }
    };

    client.connect(config.freeswitch.port, config.freeswitch.host, () => {});
    client.on('data', (data) => {
      buffer += data.toString();
      processFrames();
    });
    client.on('error', (err) => {
      reject(err);
    });
  });

const parseXmlTag = (xml, tag) => {
  const regex = new RegExp(`<${tag}>([^<]*)<\\/${tag}>`, 'i');
  const match = xml.match(regex);
  return match ? match[1].trim() : null;
};

const getGatewayStatus = async (gateway) => {
  if (!gateway) {
    throw new Error('Gateway name is required');
  }
  const profile = config.freeswitch.profile || 'external';
  const fqGateway = gateway.includes('::') ? gateway : `${profile}::${gateway}`;
  const response = await sendApiCommand(`sofia xmlstatus gateway ${fqGateway}`);
  if (/^-ERR/i.test(response)) {
    throw new Error(response.replace(/^-ERR\s*/i, '').trim());
  }
  if (/No such gateway/i.test(response)) {
    throw new Error('Gateway not found');
  }
  const state = parseXmlTag(response, 'state');
  const status = parseXmlTag(response, 'status');
  return {
    state,
    status,
    raw: response
  };
};

const defaultProfile = config.freeswitch.profile || 'external';

const registerGateway = async (gateway, profile = defaultProfile) => {
  if (!gateway) {
    return null;
  }
  return sendApiCommand(`sofia profile ${profile} register ${gateway}`);
};

const reloadXml = async () => sendApiCommand('reloadxml');

const rescanProfile = async (profile = defaultProfile) => sendApiCommand(`sofia profile ${profile} rescan`);

const killGateway = async (gateway, profile = defaultProfile) => {
  if (!gateway) {
    return null;
  }
  return sendApiCommand(`sofia profile ${profile} killgw ${gateway}`);
};

module.exports = {
  originateCall,
  callExists,
  getChannelVar,
  muteCall,
  unmuteCall,
  hangupCall,
  getGatewayStatus,
  registerGateway,
  reloadXml,
  rescanProfile,
  killGateway
};
