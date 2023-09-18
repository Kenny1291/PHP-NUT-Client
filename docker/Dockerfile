FROM ubuntu:23.10
RUN apt-get update
RUN apt-get install nut -y 
RUN echo "[dummy]\n\tdriver = dummy-ups\n\tport = evolution500.seq" >> /etc/nut/ups.conf
RUN sed -i '$ d' /etc/nut/nut.conf
RUN echo "MODE=netserver" >> /etc/nut/nut.conf
RUN echo "LISTEN 172.26.41.208 3493" >> /etc/nut/upsd.conf
RUN echo "[primaryUserName]\n\tpassword = primaryUserPassword\n\tactions = SET\n\tupsmon primary\n instcmds = all" >> /etc/nut/upsd.users
RUN echo "[secondaryUserName]\n\tpassword = secondaryUserPassword\n\tupsmon secondary" >> /etc/nut/upsd.users
RUN echo "MONITOR dummy@172.26.41.208:3493 1 primaryUserName primaryUserPassword primary"  >> /etc/nut/upsmon.conf
RUN rm -r /etc/init.d/nut-client
RUN rm -r /etc/init.d/nut-server
RUN rm -r /etc/init.d/ups-monitor
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x entrypoint.sh
COPY evolution500.seq /etc/nut/evolution500.seq
ENTRYPOINT ["/entrypoint.sh"]