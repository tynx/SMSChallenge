SMSChallenge
============

Welcome to SMSChallenge
-----------------------

SMSChallenge is a service which provides a 2 step authentication including a challenge via SMS.

The initial use for this project was for VPN user authentication via a freeradius server.
A user connects to the VPN, enters a username + PIN, then receives a password vis SMS/Text, which is then entered in a second step into the VPN authentication. So the user needs to know his username and have his phone available (two factors).

User details can be pulled from Active Directory (LDAP), SMS are sent via email, USB modem or SOAP.
The whole server can be run on a Raspberry PI!

SMSChallenge is distributed under the GNU General Public License - see chapter [License](#License) for more details. 
 
Architecture / Design
---------------------
* First step of the authentication is a normal login with a username and a password. Usernames for VPN can be distincted by a prefix. 
* If this login was successful, the SMSChallenge module sends a challenge (one-time-token) in form of an sms. This challenge has to be entered into the VPN client. This is the second authentication step.

* MySQL is used for storing the users and their passwords. The SMSChallenge-module is a simple plugin for one of the biggest RADIUS-Server (Freeradius).

* Users can be synchronised against an Active Directory (AD) Group via LDAP (done in PHP). This feature minimizes the effort of an administrator to keep track of VPN-Users which should (not) have acceess. ** Don't worry if you don't use AD/LDAP, you can work with normal SMSChallenge-internal accounts. **

* A simple WebGUI written in PHP allows administrators to manage the VPN-Users and their access.
* An even simpler CLI provides a bashy way of managing users. (Not very complete at this stage of the project).

Further Information
===================
For any further information (Installation procedure, sending methods, etc) please see the [wiki](https://github.com/tynx/SMSChallenge/wiki).

Project state
=============
The current version is in production and is working fine, therefore currently no (major) developing is in progress.

License
========
Copyright (C) 2014 Luginbühl Timon, Müller Lukas, Swisscom AG

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 

For more detail see the [license file](license.txt) or see [http://www.gnu.org/licenses](http://www.gnu.org/licenses).

[http://www.gnu.org/licenses](http://www.gnu.org/licenses)

