#SMSChallenge

Welcome to SMSChallenge
---
SMSChallenge is a service, which provides a 2 step authentication including a challenge via SMS.
SMSChallenge is distributed under the GNU General Public License - see chapter [License](#License) for more details. 
 
Architecture / Design
---
First step of the authentication is a normal login with a username and a password. Usernames for VPN can be distincted by a prefix. If this login was successful, the SMSChallenge-module sends a challenge(one-time-token) in form of an sms. This challenge has to be entered into the VPN client. This is the second authentication step.
MySQL is used for storing the users and their passwords. The SMSChallenge-module is a simple plugin for one of the biggest RADIUS-Server (Freeradius).
Users can be synced against an AD-Group via LDAP(done in PHP). This feature minimizes the effort of an administrator to keep track of VPN-Users which should (not) have acceess. Don't worry if you don't use AD/LDAP, you can work with normal SMSChallenge-internal accounts.
A simple WebGUI written in PHP allows administrators to manage the VPN-Users and their access.
An even simpler CLI provides a bashy way of managing users. (Not very complete at this stage of the project)

Installation
---

Clone the project into /opt. (Recommended, but feel free to drop it somewhere else)

Also install following packages out of your repository.

- bsd-mailx OR/AND gnokii-cli (depends on what you use, see "Chapter" [config](#config) of the module)
- libmysqlclient-dev
- gcc/cc
- freeradius
- libfreeradius-dev

---

Then create the database and functions:
```
mysql -u user -p < mysql_install.sql
mysql -u user -p < MySQLFunctions
```
It may be a good idea to make a separate mysql-user just for smschallenge.

----

Create the config for CLI/Web/Sync (the c-module is unaffected by these settings):
You can either store the config in the lib directory (where the config-loader sits) or /etc. (/etc is recommended).
```
cp smschallenge.conf.example /etc/smschallenge.conf
```
Some notes to the different settings:

- LDAP-Settings can be empty in case the syncing against the AD/LDAP is not used. Otherwise provide an LDAP-Account that has enough permissions to read out the users of a OU (Group).
  - Also what is needed for LDAP (or not if you don't use AD/LDAP) are the configs: base_ou_for_groups, groups_to_sync, ldap_schema_user
- There are 3 types of logging possible: file, mysql, syslog
  - They can be combinated (comma seperated)
  - All the settings in the category "LOG" apply for all types except: syslog_prefix and log_file
- auth_type is for the kind of authentication used for the WebGUI. In case you sync your users against AD/LDAP you may wanna use "mod_kerb_auth" for apache. Then set the auth_type to "kerberos". The other possibility is to use internal, which authenticates agains the mysql-table "user".
- password_length(min/max) is for setting the min/max length for the passwords which can be set via the GUI
- default_password is set for a user, when it's created by the admin via the WebGUI or CLI
- web_admins are the users, which are allowed to make changes to the users and view the log-messages

---

Create the first (admin) user WITHOUT LDAP:
```
php -e cli/main.php adduser
```
Default PW is 123456

Create the first (admin) user WITH LDAP:
```
php -e sync/sync.php syncuser [[YOUR USERNAME]]
```

Add the user to the admins (in conf file)! 


---

Activate WebGUI:
The easiest way is to create a symbolic link.
```
ln -s /opt/smschallenge/web /var/www/smschallenge
```

Other option is to move the folder and to adapt the include.php.
```
cp -r /opt/smschallenge/web /var/www/smschallenge
```
Edit the include.php and change the "lib_dir" to: /opt/smschallenge/lib

---

Activate Sync (in case you use AD/LDAP, otherwise skip this step):
This is basically done with a single entry in the crontab:
```
0 0 * * * /bin/bash php -e /opt/smschallenge/sync/sync.php sync
```
---

Freeradius (installing it is up to you)
This section is all about the directory "smschallenge/freeradius"
Compile the module.
```
$ make
```
This will not only compile the module, but also create the file: smschallenge. This is the config for the module. Please configure everything needed. Later in this "Chapter" you can see every send-method explained in detail. All the other properties that deserve a comment are in this list:

-  challenge_string: This will be printed at the users client when he waits for the SMS.
-  sms_class: This allows SMS's to be sent as "Flash-SMS". Note this feature is only available for the "AT"-method
-  acs_server: As you may have multiple login-methods running in one freeradius-instance, this parameter allows you to drop request which do not come from certain server.
-  account_length: Max length of the account-name(username)
-  vpn_prefix: this allows you to distinct smschallenge users, in case you have multiple vpn-authentications running.
-  max_password_length: defines the max length of the user-pw
-  code_blocks: one code block contains 3 digits. So this number multiplied by 3 is the number of digits sent via sms.

Then install the module with:
```
# make install
```
This copies the compiled module into the plugin folder of freeradius and also copies the config file into "/etc/freeradius/modules/smschallenge".

You have to tell freeradius to use smschallenge, to do so, edit the file "/etc/freeradius/sites-available/default".
```
authorize{
	preprocess
	smschallenge
}

authenticate{
	Auth-Type SMSCHALLENGE{
		smschallenge
	}
}

```

Preprocess is needed!

Start freeradius in debug mode to see details about the module/plugin:
```
# freeradius -XX
```



#Send method: at
If you want to test out your physical modem(if any is used) there is a small C-Programm which does exactly that. It contains the identical code, which is run inside the module. Compile it like this:
```
make sendsms
```
And test your modem like:
```
./sendsms 'sms test' NUMBER /dev/YOUR_DEVICE
```

It may be that your serial connection is not set up properly by linux by default. These properties worked for most of the tested modems so far:
```
stty -F /dev/YOUR_DEVICE -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke -opost -onlcr -brkint -icrnl -imaxbel min 1 time 0 ispeed 9600 ospeed 9600
```
Make sure you set these properties at startup if they're needed.

If you got issues, you can try to connect to the modem by hand and maybe you'll find the error.
```
sudo cu -l /dev/YOUR_DEVICE -s 9600
Connected.
AT
OK
AT+CFUN=1
OK
AT+CMGF=1
OK
AT+CSMP=17,167,0,16
OK
AT+CMGS="YOUR NUMBER"
> test message (note: Hit Ctrl+Z after entering your message for sending the message)
 
+CMGS: 87
 
OK
```

#Send method: mailx
You can have your codes sent by email. This is done via the binary "mailx". The mails are send to "the_users_phonenumber@your_gateway_configured_in_config.com". To test this method run:
```
echo "test message" | mailx "the_users_phonenumber@your_gateway_configured_in_config.com"
```

#Send method: gnokii
This is a binary that allows to have an easy CLI to communicate with physical modems. 
To test this method run:
```
echo "test message" | gnokii --sendsms YOUR_NUMBER
```

Sample config of a gnokii config-file
```
[global]
port = /dev/YOUR_DEVICE
model = AT
initlength = default
connection = serial
use_locking = no
serial_baudrate = 19200
serial_write_usleep = 10000
smsc_timeout = 20
```

#Send method: which one to take?
At the beginning, we were using gnokii and with most modems it works very reliable. One issue we discovered over time: the speed. It does so much resetting and configuring before each sms, that it can be too slow for what we use it in this software. That's why we wrote a "native" version. And because our modems aren't 100% reliable we use a mail-to-sms gateway as fallback (via mailx)

#License
Copyright (C) 2013 Luginbühl Timon, Müller Lukas, Swisscom AG

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 

For more detail see the [license file](license.txt) or see [http://www.gnu.org/licenses](http://www.gnu.org/licenses).


