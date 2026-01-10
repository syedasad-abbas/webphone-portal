FROM debian:bookworm-slim AS sngrep-builder

RUN apt-get update \
    && apt-get install -y --no-install-recommends sngrep ncurses-term \
    && rm -rf /var/lib/apt/lists/*

FROM syedasadabbas/voip-freeswitch-with-sounds:1.0.0

COPY --from=sngrep-builder /usr/bin/sngrep /usr/local/bin/sngrep
RUN mkdir -p /lib/x86_64-linux-gnu /lib64
COPY --from=sngrep-builder /lib/x86_64-linux-gnu/libmenuw.so.6* /lib/x86_64-linux-gnu/
COPY --from=sngrep-builder /lib/x86_64-linux-gnu/libformw.so.6* /lib/x86_64-linux-gnu/
COPY --from=sngrep-builder /lib/x86_64-linux-gnu/libpanelw.so.6* /lib/x86_64-linux-gnu/
COPY --from=sngrep-builder /lib/x86_64-linux-gnu/libncursesw.so.6* /lib/x86_64-linux-gnu/
COPY --from=sngrep-builder /lib/x86_64-linux-gnu/libpcap.so.0.8* /lib/x86_64-linux-gnu/
COPY --from=sngrep-builder /lib/x86_64-linux-gnu/libdbus-1.so.3* /lib/x86_64-linux-gnu/
COPY --from=sngrep-builder /usr/share/terminfo /usr/share/terminfo
RUN ln -sf /usr/share/terminfo/x/xterm+256color /usr/share/terminfo/x/xterm-256color

COPY conf /etc/freeswitch
