FROM alpine:3.19

RUN apk add --no-cache iptables

COPY apply.sh /usr/local/bin/apply-firewall
RUN chmod +x /usr/local/bin/apply-firewall

ENTRYPOINT ["/usr/local/bin/apply-firewall"]
