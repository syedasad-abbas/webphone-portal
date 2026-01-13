#!/bin/sh
set -e

ALLOWED_SIP_IPS="${ALLOWED_SIP_IPS:-}"
SIP_PORTS="${SIP_PORTS:-5060}"

if [ -z "$ALLOWED_SIP_IPS" ]; then
  echo "No ALLOWED_SIP_IPS set; skipping SIP firewall rules."
  exec tail -f /dev/null
fi

for port in $(echo "$SIP_PORTS" | tr ',' ' '); do
  for ip in $(echo "$ALLOWED_SIP_IPS" | tr ',' ' '); do
    iptables -C INPUT -p udp --dport "$port" -s "$ip" -j ACCEPT 2>/dev/null || \
      iptables -I INPUT 1 -p udp --dport "$port" -s "$ip" -j ACCEPT
    iptables -C INPUT -p tcp --dport "$port" -s "$ip" -j ACCEPT 2>/dev/null || \
      iptables -I INPUT 1 -p tcp --dport "$port" -s "$ip" -j ACCEPT
  done

  iptables -C INPUT -p udp --dport "$port" -j DROP 2>/dev/null || \
    iptables -A INPUT -p udp --dport "$port" -j DROP
  iptables -C INPUT -p tcp --dport "$port" -j DROP 2>/dev/null || \
    iptables -A INPUT -p tcp --dport "$port" -j DROP
done

exec tail -f /dev/null
