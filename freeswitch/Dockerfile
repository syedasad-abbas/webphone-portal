FROM syedasadabbas/voip-freeswitch-with-sounds:1.0.0

RUN apt-get update \
    && apt-get install -y --no-install-recommends sngrep \
    && rm -rf /var/lib/apt/lists/*

COPY conf /etc/freeswitch
