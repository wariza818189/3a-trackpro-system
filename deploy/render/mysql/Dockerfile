# syntax=docker/dockerfile:1
# Pin the inspected official 8.4.11 image to avoid a database upgrade on rebuild.
FROM mysql:8.4@sha256:6ea90827b1100f8f2ae306a539f86d2c264a26ed435a2a9f75551dd5c3aeb242

# /etc/my.cnf in the official image includes this directory.
# TLS material is supplied by the existing persistent disk, never the image.
COPY --chmod=0644 trackpro-tls.cnf /etc/mysql/conf.d/trackpro-tls.cnf

# Inherit the official docker-entrypoint.sh and mysqld CMD unchanged.
