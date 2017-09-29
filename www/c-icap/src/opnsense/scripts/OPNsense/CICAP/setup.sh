#!/bin/sh

mkdir -p /var/run/c-icap
chown -R c_icap:c_icap /var/run/c-icap
chmod 750 /var/run/c-icap

mkdir -p /var/log/c-icap
chown -R c_icap:c_icap /var/log/c-icap
chmod 750 /var/log/c-icap

# check which services to enable
if [ -f /etc/rc.conf ]; then
	. /etc/rc.conf
fi
if [ -f /etc/rc.conf.local ]; then
	. /etc/rc.conf.local
fi
for RC_CONF in $(find /etc/rc.conf.d -type f); do
	. ${RC_CONF}
done

rc_enabled()
{
	rc_filename=${1}
	name=${2}

	# check if service has a name
	if [ -z "${name}" ]; then
		echo "Error: no name set in ${rc_filename}"
		return 1
	fi

	# check if service has a variable
	eval "$(grep "^rcvar[[:blank:]]*=" ${rc_filename})"
	if [ -z "${rcvar}" ]; then
		# FreeBSD does this, leave here for debugging
		#echo "Error: no rcvar set in $rc_filename"
		return 1
	fi

	# check if service is enabled
	eval "enabled=\$${rcvar}"
	if [ "${enabled}" != "YES" ]; then
		return 1
	fi

	return 0
}

rc_filename="/usr/local/etc/rc.d/clamav-clamd"
eval "$(grep "^name[[:blank:]]*=" ${rc_filename})"

if ! rc_enabled ${rc_filename} ${name}; then
	return 0
fi

/usr/local/opnsense/scripts/OPNsense/ClamAV/setup.sh
${rc_filename} start
