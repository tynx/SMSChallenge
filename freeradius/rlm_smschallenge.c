/**
 * Copyright (C) 2013 Luginbühl Timon, Müller Lukas, Swisscom AG
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For more informations see the license file or see <http://www.gnu.org/licenses/>.
 */
 
#include <freeradius/ident.h>
RCSID("$Id$")
#include <freeradius/radiusd.h>
#include <freeradius/modules.h>
#include <math.h>
#include <ctype.h>
#include <mysql/mysql.h>
#include <time.h>
#include <syslog.h>
#include <fcntl.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <string.h>
#include <termios.h>
#include <stdarg.h>

/* If you don't get all informations in the request		if( strcmp(vpnprefix, prefix) != 0 ){ packet
  delete following line or more likely change it to: #define NDEBUG */
#undef NDEBUG

/* For the configurations of the module... */
typedef struct rlm_smschallenge_t{
	char	*mysql_username;
	char	*mysql_password;
	char	*mysql_host;
	char	*mysql_database;
	char	*challenge_string;
	char	*send_method;
	char	*send_email_gw;
	char	*acs_server;
	char	*acs_server2;
	char	*acs_server3;
	char	*acs_server4;
	int		account_length;
	char	*vpn_prefix;
	int		max_password_length;
	int		code_blocks;
	char	*modem_port;
	int		sms_class;
	char	*send_method_fallback;
} rlm_smschallenge_t;

/* Struct for saving needed information about an sms */
struct sms_content{
	char	*phone_number;
	char	*message;
	int	sms_class;
};

/* Still configurations stuff... */
static const CONF_PARSER module_config[] = {
	{ "mysql_username",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,mysql_username),		NULL,	NULL},
	{ "mysql_password",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,mysql_password),		NULL,	NULL},
	{ "mysql_host",				PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,mysql_host),			NULL,	NULL},
	{ "mysql_database",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,mysql_database),		NULL,	NULL},
	{ "challenge_string",		PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,challenge_string),		NULL,	NULL},
	{ "send_method",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,send_method),			NULL,	NULL},
	{ "send_email_gw",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,send_email_gw),			NULL,	NULL},
	{ "acs_server",				PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,acs_server),			NULL,	NULL},
	{ "acs_server2",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,acs_server2),			NULL,	NULL},
	{ "acs_server3",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,acs_server3),			NULL,	NULL},
	{ "acs_server4",			PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,acs_server4),			NULL,	NULL},
	{ "account_length",			PW_TYPE_INTEGER,	offsetof(rlm_smschallenge_t,account_length),		NULL,	NULL},
	{ "vpn_prefix",				PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,vpn_prefix),			NULL,	NULL},
	{ "max_password_length",	PW_TYPE_INTEGER,	offsetof(rlm_smschallenge_t,max_password_length),	NULL,	NULL},
	{ "code_blocks",			PW_TYPE_INTEGER,	offsetof(rlm_smschallenge_t,code_blocks),			NULL,	NULL},
	{ "modem_port",				PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,modem_port),			NULL,	NULL},
	{ "sms_class",				PW_TYPE_INTEGER,	offsetof(rlm_smschallenge_t,sms_class),				NULL,	"-1"},
	{ "send_method_fallback",	PW_TYPE_STRING_PTR,	offsetof(rlm_smschallenge_t,send_method_fallback),	NULL,	NULL},
	{ NULL, -1, 0, NULL, NULL }		/* end the list */
};


/* wrapper for nanosleep */
int __nsleep(const struct timespec *req, struct timespec *rem){
	struct timespec temp_rem;
	if(nanosleep(req,rem)==-1){
		__nsleep(rem,&temp_rem);
	}
	return 1;
}

/* usefull interface which implements milisleep*/
int msleep(unsigned long milisec){
	struct timespec req={0},rem={0};
	time_t sec=(int)(milisec/1000);
	milisec=milisec-(sec*1000);
	req.tv_sec=sec;
	req.tv_nsec=milisec*1000000L;
	__nsleep(&req,&rem);
	return 1;
}

/*
 * log to syslog, radlog, RDebug
 * radlog with priority = 2
 *
 * opt = combined octal value
 * 1 = radlog
 * 2 = syslog
 * 4 = RDebug
 *
 * loglvl ( for syslog)
 * 1 = LOG_ERR  2 = LOG_WARNING  3 = LOG_INFO 4 = LOG_DEBUG
 *
 * *request is needed for RDebug.
 *
 * msg = the Message to log ( with %s, %d etc...)
 *
 * ...  = args printf style
 *
 */
void logger(int opt, int loglvl, REQUEST *request, char *msg, ...){
	char log_string[1024];
	va_list ap;
	va_start(ap, msg);
	vsprintf(log_string, msg, ap);
	va_end(ap);

	openlog("smschallenge", LOG_CONS | LOG_PID | LOG_NDELAY, LOG_LOCAL1 );

	if(opt == 1 || opt == 3 || opt == 5 || opt == 7){
		radlog(2,"%s",log_string);
	}
	if(opt == 2 || opt == 3 || opt== 6 || opt == 7){
		switch(loglvl){;
			case 1: syslog(LOG_ERR, "%s",log_string);		break;
			case 2: syslog(LOG_WARNING, "%s",log_string);	break;
			case 3: syslog(LOG_INFO, "%s",log_string);		break;
			case 4: syslog(LOG_DEBUG, "%s",log_string);		break;
			default: syslog(LOG_WARNING, "%s",log_string);	break;
		}
	}
	if(opt == 4 || opt == 5 || opt == 6 || opt == 7){
		RDEBUG("%s", log_string);
	}

	closelog();
}


/* connect mysql and check for errors*/
int establish_sql_connection(MYSQL **conn, rlm_smschallenge_t *inst, REQUEST *request ){

	/* Check if we got all needed information from the config file. If not die otherwise connect (and die if wrong information) */
	if( inst->mysql_host == NULL || inst->mysql_username == NULL || inst->mysql_password == NULL || inst->mysql_database == NULL ){
		logger(7, 1, request, "Not all MySQL information is given! Rejecting the user.");
		return RLM_MODULE_FAIL;
	}

	*conn = mysql_init( NULL );
	/* Went something wrong? */
	if( conn == NULL ){
		logger(7, 1, request, "Could not allocate a new mysql-object. Out of memory?");
		return RLM_MODULE_FAIL;
	}

	/* Connect to mysql-server or die */
	if (!mysql_real_connect(*conn, inst->mysql_host, inst->mysql_username, inst->mysql_password, inst->mysql_database, 0, NULL, 0)) {
		logger(7, 1, request, "Could not connect to the MySQL-Host: %s", mysql_error(*conn));
		return RLM_MODULE_FAIL;
	}

	// everything ok
	return 0;
}

/* select and check for errors. result will be written to the given ptr*/
int sql_select(MYSQL **conn, char *query, MYSQL_RES **res, REQUEST *request){

	/* If something bad happend, debug it otherwise use the results */
	if (mysql_query(*conn, query)) {
		logger(3, 2, NULL, "mysql-error:%s", mysql_error(*conn));
		logger(2, 1, NULL, "Something went wrong while running this query: %s", query);
		mysql_close(*conn);
		return RLM_MODULE_NOOP;
	}

	*res = mysql_use_result( *conn );
	/* Everything fine? */
	if( res == NULL ){
		logger(3, 2, NULL, "Could not use mysql_results. Possible reasons: Out of memory, The MySQL server has gone away?");
		mysql_close(*conn);
		return RLM_MODULE_FAIL;
	}
	return 0;
}

/* Get the answer (only one row because of mysql-function) */
int get_answer(MYSQL_RES **res, int *loggedin){
	MYSQL_ROW row;
	while ((row = mysql_fetch_row(*res)) != NULL){
		//if somehow we don't get an usable answer we overwrite it with 0
		if( row[0] == NULL ){
			row[0] = "0";
		}
		if( strcmp( row[0], "1" ) == 0 ){
			*loggedin = 1;
		}
	}
	mysql_free_result(*res);
	return 0;
}

/* Here we go with the first function, which builds as a code for a
 * challenge to this format: xxx xxx xxx (groups-number => blocks_number)
 */
char *getCode( int blocks_number) {
	/* alloc one piece of char (2x because of hex) */
	char *str=calloc( 1, 2*sizeof( char ) );
	if( str == NULL ){
		return "";
	}
	/* The according number of blocks */
	int blocks[blocks_number];
	/* Our final code will be saved in here */
	char *code = malloc( sizeof(char) * (blocks_number * 4 -1) );
	if( code == NULL ){
		return "";
	}
	/* reset it, otherwise strange magic will happen! */
	code[0] = '\0';
	/* Our fopen-var */
	FILE *fp;
	int i=0, j=0;

	/* Set all values to zero */
	for( i=0; i<blocks_number; i++ )
		blocks[i] = 0;

	/* open the urandom */
	fp=fopen( "/dev/urandom", "r" );

	/* small possibility, but if... */
	if ( fp == NULL )
		return "";

	/* as many as needed */
	for( i=0; i<(blocks_number*3); i++){
		/* get a char of urandom and save it as hex */
		sprintf(str,"%02x",fgetc(fp));

		/* make an int out of it and add it to the according block */
		blocks[j] += (int)strtol(str, NULL, 16);

		/* If we have 3 values added, go to the next block */
		if( (i+1) % 3 == 0 && i != 0 ){
			j++;
		}
	}

	for( i=0 ; i<blocks_number ; i++ ){
		/* Is the value smaller than 100 or 10, ADD the according number
		 of zeros! */
		if( blocks[i] < 100 ){
			if( blocks[i] < 10 ){
				sprintf(code, "%s00%i", code, blocks[i]);
			}else{
				sprintf(code, "%s0%i", code, blocks[i]);
			}
		}else{
			sprintf(code, "%s%i", code, blocks[i]);
		}
		/* The last one doesn't need a space */
		if( i < blocks_number -1 ){
			sprintf( code, "%s ", code );
		}
	}

	/* Close the "file" / filepointer */
	fclose(fp);

	/* Finally give back the finished code */
	return code;
}

int send_sms_via_mailx(struct sms_content sms, char *mail_gateway){
	char command[1024] = "";
	sprintf(command, "echo \"%s\" | mailx \"%s@%s\"", sms.message, sms.phone_number, mail_gateway);
	return system(command);
}

int send_sms_via_gammu(struct sms_content sms){
	char command[1024] = "";
	sprintf(command, "echo \"%s\" | gammu --sendsms TEXT %s", sms.message, sms.phone_number );
	return system(command);
}

int send_command(int fd, char* at_command, int timeout) {
	char buffer[255];	/* Input buffer */
	char *bufptr = "";	/* Current char in buffer */
	int nbytes;		/* Number of bytes read */
	int tries;		/* Number of tries so far */
	int sent = 0;		/* If the command was sent within the 3 tries */
	char* line = NULL;	/* begining of line pointer for response parsing */
	time_t start_time;	/* dont wait for too long - start*/
	time_t current_time; 	/* dont wait for too long - current */

	// empty the serial-output
	nbytes = read(fd, bufptr, buffer + sizeof(buffer) - bufptr - 1);

	// set current time
	start_time = time(NULL);
	current_time = time(NULL);

	// Check if something went wrong with time()
	if( start_time == (time_t)(-1) || current_time == (time_t)(-1) ){
		logger(2, 1, NULL, "Error, couldn't get the time => time() failed");
		return (-1);
	}

	// Try 3 times...
	for (tries = 0; tries < 3; tries ++){
		/* send the AT command */
		if (write(fd, at_command, strlen(at_command)) == strlen(at_command)){
			/* command was succesfully sent */
			sent = 1;
			break;
		}
	}

	/* If the command was not send, we quit */
	if( sent != 1 ){
		return (-2);
	}

	/* Give the modem some time to breath (100ms) */
	msleep(100);
	/* Try to read every 100ms until we reached the timeout */
	while ( difftime(current_time, start_time) <= timeout){
		current_time = time(NULL);
		if (current_time == (time_t)(-1)) {
			logger(2, 1, NULL, "Error, couldn't get cur_time => time() failed");
			return (-1);
		}

		/* Reset the buffer and read-bytes-count every time */
		bufptr = buffer;
		nbytes = 0;

		/* Read from serial port */
		nbytes = read(fd, bufptr, buffer + sizeof(buffer) - bufptr - 1);

		if(nbytes != 0){
			/* We recieved some data, let's validate them */
			bufptr += nbytes;
			line = strtok(buffer, "\n\r");
			/* Go trough every line */
			do{
				/* Uncomment for useful debug information */
				logger(2, 4, NULL, "Modem-response: [%s]", line);
				if( line == NULL ){
					/* Something went terrible wrong, only solutions: quit */
					logger(2, 1, NULL, "There was no answer from the modem.");
					return (-3);
				}
				/* If the line equals OK or > (prombt) we're good for now */
				if ((strncmp(line, "OK", 2) == 0) || line[0] == '>' ) {
					/* Quit with success */
					return (0);
				}
				if(strncmp(line, "ERROR", 5) == 0 || strncmp(line, "+CMS ERROR", 10) == 0){
					/* Definitely something wrong but modem is not hanging*/
					logger(2, 1, NULL, "Received an error from the modem: [%s]", line);
					return (-4);
				}
			} while((line = strtok(NULL, "\n\r")));
		}
		/* Give the modem some time (100ms) */
		msleep(100);
	}
	/* If we got here, something went wrong while readin the data:
		either we didn't recieve and OK or >, or the modem timed-out, we quit */
	logger(2, 1, NULL, "Modem-response: either no data or timeout");
	return (-5);
}

int open_serial_device(char *device){
	int fd;					/* The file-descriptor */
	struct termios options;	/* The options-struct for the connection */

	/* Try to open the serial port */
	if((fd = open(device, O_RDWR | O_NOCTTY | O_NDELAY)) == -1){
		/* Failed to open device */
		return (-1);
	}

	/* Get the options of the serial-connection*/
	tcgetattr(fd, &options);

	/* Replace the baud-rate */
	cfsetispeed(&options, B9600);
	cfsetospeed(&options, B9600);
	/* Replace the hw flow control to _NO_ hw flow control */
	options.c_cflag |= CRTSCTS;
	/* Set the port to local otherwise control-characters will produce a hangup */
	options.c_cflag |= CLOCAL;

	/* Set the new options */
	tcsetattr(fd, TCSANOW, &options);

	/* We're done, give back the file-descriptor */
	return fd;
}

int send_sms_via_at(struct sms_content sms, char *device){
	int fd;							/* File-descriptor of the serial connection */
	int file_locked = 0;			/* If locking of the file was successful */
	int timeout = 4;				/* The timeout for the AT-command in secs 3 was ok with serial modem */
	int timeout_file = 4;			/* The timeout for the flock in secs */
	int fail = 0;					/* return value */
	int ret = 0;					/* Temporary return values */
	int flags = 0;					/* fcntl return value/flags are saved in here */
	time_t start_time;				/* Dont wait for to long - start */
	time_t current_time;			/* Dont wait for to long - current */
	char at_command_number[1024];	/* The AT-command for setting the number */
	char at_command_message[1024];	/* The AT-command for setting the message */
	char at_command_class[1024];	/* The AT-command for setting the class */

	/* Build string for the AT-command for setting the number */
	at_command_number[0] = '\0';
	sprintf(at_command_number, "AT+CMGS=\"%s\"\r", sms.phone_number);
	/* Build string for the AT-command for setting the message */
	at_command_message[0] = '\0';
	/* Ctrl-Z => Ascii 26 => hex 1A (final char for sending the sms) */
	sprintf(at_command_message, "%s\x1A", sms.message);
	/* Build string for the AT-command for setting the sms-class */
	at_command_class[0] = '\0';
	if( sms.sms_class == 0 ){ // Class 0 is flash message (4th bit to 15+1=>16)
		strcpy( at_command_class, "AT+CSMP=17,167,0,16\r");
	}else{ // Normal SMS (4th bit is 0)
		strcpy( at_command_class, "AT+CSMP=17,167,0,0\r");
	}

	/* Open the device and check if the opening was successful*/
	if((fd = open_serial_device(device)) == -1)
		fail = -1;

	/* Create a file-lock struct */
	struct flock fl = {F_WRLCK, SEEK_SET, 0, 0, getpid() };

	/* Set the current time */
	start_time = time(NULL);
	current_time = time(NULL);

	// Check if something went wrong with time()
	if( start_time == (time_t)(-1) || current_time == (time_t)(-1) ){
		logger(2, 1, NULL, "Error, couldn't get start_time/current_time => time() failed");
		return (-2);
	}

	/* If nothing failed and the timeout isn't reached we continue to check */
	while ( difftime(current_time, start_time) <= timeout_file && fail == 0 ){
		// Get current time or fail
		current_time = time(NULL);
		if (current_time == (time_t)(-1)) {
			logger(2, 1, NULL, "Error, couldn't get current_time => time() failed");
			return (-2);
		}
		/* Try to lock the file */
		if( fcntl(fd, F_SETLK, &fl) == 0 ){
			/* If it was a success we break */
			file_locked = 1;
			break;
		}
		/* Otherwise we wait for 100ms and try again */
		msleep(100);
	}

	/* If the locking wasn't successful, we set the according fail */
	if( file_locked != 1 )
		fail = -3;



	/* Send all the AT-commands */
	if (fail == 0 && send_command(fd, "AT\r", timeout) != 0)
		fail = -4;
	if (fail == 0 && send_command(fd, "AT+CFUN=1\r", timeout) != 0)
		fail = -5;
	if (fail == 0 && send_command(fd, "AT+CMGF=1\r", timeout) != 0)
		fail = -6;
	if( fail == 0 ){
		ret = send_command(fd, "AT+CSCS=\"ASCII\"\r", timeout);
		if(ret == -4 ){
			/* ASCII is probably not supported, try GSM */
                	if( send_command(fd, "AT+CSCS=\"GSM\"\r", timeout) != 0 )
                        	fail = -7;
		}else if(ret != 0){
			fail = -7;
		}
	}
	if (fail == 0 && send_command(fd, at_command_class, timeout) != 0)
		fail = -8;
	if (fail == 0 && send_command(fd, at_command_number, timeout) != 0)
		fail = -9;
	if (fail == 0 && send_command(fd, at_command_message, timeout) != 0 )
		fail = -10;

	/* Change the file-lock struct, so it won't block anymore */
	fl.l_type = F_UNLCK;

	/* Set the unlocking struct */
	fcntl(fd, F_SETLK, &fl);

	// Check if unlocking was done...
	flags = fcntl(fd, F_GETFD);
	if (flags == -1) {
		fail = -11;
	}

	/* close the file-descriptor */
	close(fd);

	/* Give back error-code in case of an error */
	if( fail != 0 ){
		return (fail);
	}

	/* If we got here, everything is fine */
	return (0);
}

/* This function allocates the according space and reads the config data */
static int smschallenge_instantiate(CONF_SECTION *conf, void **instance){
	rlm_smschallenge_t *data;

	/* Set up a storage area for instance data */
	data = rad_malloc(sizeof(*data));
	/* If failed, die */
	if (!data) {
		return -1;
	}

	/* otherwise set it */
	memset(data, 0, sizeof(*data));

	/* If the configuration parameters can't be parsed, then die */
	if (cf_section_parse(conf, data, module_config) < 0) {
		free(data);
		return -1;
	}

	/* finally set it */
	*instance = data;

	return 0;
}

/* This function makes the basic login ( username + password ) and
 * creates a new code which will be sent as an email, in our case
 * as sms.
 */
static int smschallenge_authorize(void *instance, REQUEST *request ){
	/* MySQL Vars */
	MYSQL *conn = NULL;
	MYSQL_RES *res = NULL;
	MYSQL_ROW row;
	char query[1024];

	/* Value pairs */
	VALUE_PAIR *state;
	VALUE_PAIR *reply;

	/* Time stuff */
	struct tm *local;
	time_t t;
	t = time(NULL);
	local = localtime(&t);

	/* loggin, and setting vars */
	int loggedin = 0, smsret = -1, validACS = 0;
	//long unsigned phone_number = 0;
	long long phone_number = 0;
	char phone_number_str[20] = "";
	char *code = "";
	//char sms[1024] = "";
	int sms_sent = 0;
	rlm_smschallenge_t *inst = instance;
	// ret_val for sql functions
	int ret_val;

	// If we are missing the username we don't want to create a seg-fault
	if ( !pairfind( request->packet->vps, PW_USER_NAME ) ) {
		logger(4, 0, request, "No PW_USER_NAME given, smschallenge is going to reject the user.");
		return RLM_MODULE_NOOP;
	}

	/* If we are missing the password we don't want to create a
	 * seg-fault ( e.g. happens with EAP-Requests)
	 */
	if ( !pairfind( request->packet->vps, PW_USER_PASSWORD ) ) {
		logger(4, 0, request, "No PW_USER_PASSWORD given, smschallenge is going to reject the user.");
		return RLM_MODULE_NOOP;
	}

	/* If we didn't got any configs, then we take the defaults as fallback*/
	if( inst->account_length == 0 ){
		inst->account_length = 20;
		logger(7, 2, request, "There was no account-length given. fallback 20 is used.");
	}

	/* Check the password_length (which should be set in the module-config) */
	if( inst->max_password_length == 0 ){
		inst->max_password_length = 12;
		logger(7, 2, request, "There was no max password-length given. fallback 12 is used.");
	}

	/* Check the code_blocks (which should be set in the module-config) */
	if( inst->code_blocks == 0 ){
		inst->code_blocks = 2;
		logger(7, 2, request, "There was no code-blocks given. fallback 2 is used.");
	}

	/*
	 * if we got an mac-address we strictly want to ignore/skip it, before doing anything else.
	 * Fist check if we got the right length for a mac-address
	 */
	if(strlen(request->username->vp_strvalue)==12){

		/* The non-hex characters will be saved in here */
		char *invalid_characters = '\0';

		/* Parse the username as into an unsigned long. We ignore the value which
		 * would be returned, but look at the characters which failed. If no
		 * invalid character is found, we know, that the username only contains
		 * hexadecimals-characters
		 */
		strtoul(request->username->vp_strvalue, &invalid_characters, 16); // hex

		/* If the string was successfully converted to long from hex-base we
		 * can assume, that we have a MAC-Address. We ignore the request and return
		 * with NOOP (Module is fine, but we have nothing to do)
		 */
		if(strcmp(invalid_characters, "")==0){
			logger(3, 3, NULL, "Detected MAC-Address in username. Skipping the auth-request.\n");
			return RLM_MODULE_NOOP;
		}
	}

	/* if user or pw is too long, we cut them to the according length
	 * note that strnlen does NOT terminate extra long strings, hence the hack below..
	 * does instead of
	 * strncpy( password, request->password->vp_strvalue, inst->password_length +1 );
	 * we do the checking below
	 */

	/* Prevent buffer overflow through long user/pw */
	char username[inst->account_length];
	char password[inst->max_password_length];
	/* Prevents SQL-Injection when using following vars (will be escaped) */
	char username_escaped[(2 * strlen(username))+1];
	char password_escaped[(2 * strlen(password))+1];

	if (strlen(request->username->vp_strvalue) < inst->account_length ) {
		strncpy( username, request->username->vp_strvalue, strlen(request->username->vp_strvalue) +1);
	} else {
		strncpy( username, request->username->vp_strvalue, inst->account_length);
		username[inst->account_length]='\0';
		logger(1, 0, NULL, "Username too long: truncated to %d characters", inst->account_length);
	}

	if (strlen(request->password->vp_strvalue) <= inst->max_password_length ) {
		strncpy( password, request->password->vp_strvalue, strlen(request->password->vp_strvalue) +1);
	} else {
		strncpy( password, request->password->vp_strvalue, inst->max_password_length);
		password[inst->max_password_length]='\0';
		logger(1, 0, NULL, "Password too long: truncated to %d characters", inst->max_password_length);
	}

	/* DEBUG the ACS-Server we got */
	logger(4, 0, request, "Request with IP: %s", inet_ntoa( request->packet->src_ipaddr.ipaddr.ip4addr ) );

	// If no ACS-Server is configured we wont validate it.
	if(inst->acs_server != NULL || inst->acs_server2 != NULL || inst->acs_server3 != NULL || inst->acs_server4 != NULL){
		/* Lookup the radius sender/ACS-Host: is it in the list? otherwise we will answer with a reject */
		if( inst->acs_server != NULL && strcmp( inst->acs_server, inet_ntoa( request->packet->src_ipaddr.ipaddr.ip4addr )) ==0){
			validACS += 1;
		}
		if( inst->acs_server2 != NULL && strcmp( inst->acs_server2, inet_ntoa( request->packet->src_ipaddr.ipaddr.ip4addr )) ==0){
			validACS += 1;
		}
		if( inst->acs_server3 != NULL && strcmp( inst->acs_server3, inet_ntoa( request->packet->src_ipaddr.ipaddr.ip4addr )) ==0){
			validACS += 1;
		}
		if( inst->acs_server4 != NULL && strcmp( inst->acs_server4, inet_ntoa( request->packet->src_ipaddr.ipaddr.ip4addr )) ==0){
			validACS += 1;
		}

		// We were not able to found a ACS-Server or the request is from another one...(not on the list)
		if( validACS == 0 ){
			logger(7, 3, request, "Request from %s - ignored, not in acs_server list", inet_ntoa( request->packet->src_ipaddr.ipaddr.ip4addr ) );
			return RLM_MODULE_NOOP;
		}else if( validACS != 1 ){
			logger(7, 3, request, "Something is weird with the ACS-server settings. maybe a duplicate?");
			return RLM_MODULE_NOOP;
		}
	}

	/* Look for a challenge answer, if yes set the auth-typedef
	   to SMSCHALLENGE to run later on the function smschallenge_authenticate */
	state =  pairfind(request->packet->vps, PW_STATE);
	if (state != NULL) {
		VALUE_PAIR *authtype;

		/* Do something to set the Auth-Type */
		authtype = pairmake("Auth-Type", "SMSCHALLENGE", T_OP_SET);
		pairadd(&request->config_items, authtype);

		/* everything is fine give it back */
		logger(4, 0, request, "Challenge recieved at %s", asctime(local));
		return RLM_MODULE_OK;
	}

	// if set, check the prefix
	if(inst->vpn_prefix != NULL){
		int prefix_length = 0;
		char prefix[1024] = "";
		char vpnprefix[1024] = "";

		prefix_length = strlen(inst->vpn_prefix);
		strncpy(prefix, username, prefix_length);
		strncpy(vpnprefix, inst->vpn_prefix, prefix_length);
		int j = 0;
		for(j = 0; j < prefix_length; j++){
			prefix[j]=tolower(prefix[j]);
			vpnprefix[j]=tolower(vpnprefix[j]);
		}
		if( strcmp(vpnprefix, prefix) != 0 ){
			logger(7, 1, request, "VPN Prefix wrong, authentication failed user=%s", request->username->vp_strvalue);
			return RLM_MODULE_REJECT;
		}
		strcpy(username, &username[3]);
	}

	/* connect sql*/
	if((ret_val = establish_sql_connection(&conn, inst, request))!= 0){
		return ret_val;
	}

	/* Make sure no runnable mysql-query is in the username */

	mysql_real_escape_string(conn, username_escaped, username, strlen(username) );
	logger(7, 3, request, "username esacped: %s", username_escaped);
	/* And int the Password also */
	mysql_real_escape_string(conn, password_escaped, password, strlen(password));

	/* Build the query for the base-login (mysql-function) */
	sprintf( query, "SELECT base_login('%s', '%s');", username_escaped, password_escaped );

	if((ret_val = sql_select(&conn, query, &res, request)) != 0){
		return ret_val;
	}

	msleep(100);

	/* get the answer*/
	get_answer(&res, &loggedin);
	/* If no or more users are found for this login, we have a problem */
	if( loggedin != 1 ){
		logger(7, 3, request, "Base login failed, user=%s", request->username->vp_strvalue);
		mysql_close(conn);
		return RLM_MODULE_NOOP;
	}

	/* Create new code */
	code = getCode(inst->code_blocks);

	/* Save the new code into the database */
	sprintf( query, "SELECT set_code('%s', '%s');", username_escaped, code );

	if((ret_val = sql_select(&conn, query, &res, request)) != 0){
		return ret_val;
	}

	/* Get the answer */
	get_answer(&res, &loggedin);


	/* The query to get phonenumber */
	sprintf( query, "SELECT get_phone_number('%s');", username_escaped );

	if((ret_val = sql_select(&conn, query, &res, request)) != 0){
		return ret_val;
	}

	/* Get the phonenumber */
	row = mysql_fetch_row(res);
	//if somehow we don't get an usable answer we overwrite it with ''
	if( row == NULL ){
		logger(7, 1, request, "MySQL didn't gave an phonenumber back. So we are quitting.");
		return RLM_MODULE_FAIL;
	}
	else{ // it's seems that we got an number
		phone_number = atoll( row[0] );   // note 'll' for long long, to ensure 64 bits
		if( phone_number != 0 ){
			sprintf(phone_number_str, "00%lld", phone_number);
		}else{
			logger(7, 2, request, "There was no or not a valid mobile-number provided, so we reject the user.");
			return RLM_MODULE_NOOP;
		}
	}
	logger(4, 0, request, "phone_number_str=%s", phone_number_str);

	/* Close the mysql-connection */
	mysql_free_result(res);
	mysql_close(conn);

	//if somehow we don't get an usable send_method we ar going to using mailx as fallback
	if( inst->send_method == NULL || (
		strcmp(inst->send_method, "at") != 0 &&
		strcmp(inst->send_method, "gammu") != 0 &&
		strcmp(inst->send_method, "mailx") != 0 )){

		logger(7, 1, request, "There was no valid send_method given. Going to use the fallback.");

		if(inst->send_method_fallback == NULL || (
		strcmp(inst->send_method_fallback, "at") != 0 &&
		strcmp(inst->send_method_fallback, "gammu") != 0 &&
		strcmp(inst->send_method_fallback, "mailx") != 0 )){
			logger(7, 1, request, "There was no valid send_method_fallback given. Rejecting the user, we don't have any way to send sms.");
			return RLM_MODULE_FAIL;
		}else{
			inst->send_method = inst->send_method_fallback;
		}
	}

	/* Avoid Seg-faults! */
	if( inst->send_method_fallback == NULL ){
		inst->send_method_fallback = "";
	}

	/* if the send method (also the fallback) is mailx, we need a valid email_gw */
	if( (inst->send_email_gw == NULL || strcmp( inst->send_email_gw, "") == 0) && 
		(strcmp( inst->send_method, "mailx" ) == 0 || strcmp( inst->send_method_fallback, "mailx" ) == 0 ) ){
		logger(7, 1, request, "No sms gateway given for sending via mailx. We cant send an sms -> reject user");
		return RLM_MODULE_FAIL;
	}

	/* if the send method (also the fallback) is at, we need a valid modem */
	if( inst->modem_port == NULL && (strcmp(inst->send_method, "at") == 0 || strcmp(inst->send_method_fallback, "at") == 0) ){
		logger(7, 1, request, "No modem-port was given. AT send_method is not working without an modem-port -> reject user");
		return RLM_MODULE_FAIL;
	}

	/* Check if we got a valid sms_class, otherwise use default => 1 */
	if( inst->sms_class != 0 && inst->sms_class != 1 ){
		inst->sms_class = 1;
		logger(7, 2, request, "Valid SMS-Classes are: 1 or 0, no valid was given, using fallback: 1");
	}

	/* We should now have all the information for sending the sms. lets build up the struct
	 * for that */
	struct sms_content sms_info;
	sms_info.message = code;
	sms_info.phone_number = phone_number_str;
	sms_info.sms_class = inst->sms_class;

	/* Send via gammu */
	if( strcmp( inst->send_method, "gammu" ) == 0 ){
		/* Since gammu does not like double zero instead of + we have to do a replacement */
		if( strncmp(phone_number_str, "00", 2) == 0 ){
			char phone_number_str_replaced[1024];
			//strncpy(phone_number_str_replaced, "+", 1);
			phone_number_str_replaced[0] = '+';
			phone_number_str_replaced[1] = '\0';
			strncat(phone_number_str_replaced, &phone_number_str[2], strlen(phone_number_str)-2);
			sms_info.phone_number = phone_number_str_replaced;
		}
		smsret = send_sms_via_gammu(sms_info);
		/* Set log-messages, according to the return-value */
		if( smsret == 0 ){
			sms_sent = 1;
			logger(7, 3, request, "SMS \"%s\" sent via gammu to number %s", sms_info.message, sms_info.phone_number);
		}else{
			logger(7, 1, request, "(Gammu) Sending SMS \"%s\" to number %s failed!", sms_info.message, sms_info.phone_number);
		}
	}

	/* Send via at-commands */
	if( strcmp( inst->send_method, "at" ) == 0 ){
		smsret = send_sms_via_at(sms_info, inst->modem_port);
		/* Set log-messages, according to the return-value */
		if( smsret == 0 ){
			sms_sent = 1;
			logger(7, 3, request, "SMS \"%s\" sent via at-commands to number %s", sms_info.message, sms_info.phone_number);
		}else{
			char *reason;
			switch(smsret){;
				case -1 : reason = "Open failed! Valid modem?"; break;
				case -2 : reason = "Could not get the current time => time() failed."; break;
				case -3 : reason = "Locking failed! Another process is using the modem? Or wrong path/file for modem?"; break;
				case -4 : reason = "AT failed! Modem down?"; break;
				case -5 : reason = "AT+CFUN failed! Modem down?"; break;
				case -6 : reason = "AT+CMGF failed! Modem down?"; break;
				case -7 : reason = "AT+CSCS failed! Modem down?"; break;
				case -8 : reason = "AT+CSMP (SMS-Class) failed! Modem down?"; break;
				case -9 : reason = "Setting number failed! Modem down?"; break;
				case -10 : reason = "Setting/Sending message failed! Modem down?"; break;
				case -11 : reason = "Unlocking the file failed!"; break;
				default : reason = "Return-Code not recognized!"; break;
			}
			logger(7, 1, request, "(AT-commands) Sending SMS \"%s\" to number %s failed! Reason: %s", sms_info.message, sms_info.phone_number, reason );
		}
	}

	/* Send via mailx */
	if( strcmp( inst->send_method, "mailx" ) == 0 ){
		smsret = send_sms_via_mailx(sms_info, inst->send_email_gw);
		/* Set log-messages, according to the return-value */
		if( smsret == 0 ){
			sms_sent = 1;
			logger(7, 3, request, "SMS \"%s\" sent via mailx to number %s", sms_info.message, sms_info.phone_number);
		}else{
			logger(7, 1, request, "(Mailx) Sending SMS \"%s\" to number %s failed!", sms_info.message, sms_info.phone_number);
		}
	}

	/* Check if we got an valid fallback send_method */
	if( inst->send_method_fallback != NULL && (
		strcmp(inst->send_method_fallback, "at") == 0 ||
		strcmp(inst->send_method_fallback, "gammu") == 0 ||
		strcmp(inst->send_method_fallback, "mailx") == 0 )){

		/* If the sms was not send and the fallback is gammu, send it via gammu */
		if( strcmp( inst->send_method_fallback, "gammu" ) == 0 && sms_sent == 0 ){
			smsret = send_sms_via_gammu(sms_info);
			/* Set log-messages, according to the return-value */
			if( smsret == 0 ){
				sms_sent = 1;
				logger(7,3, request, "FALLBACK: SMS \"%s\" sent via gammu to number %s", sms_info.message, sms_info.phone_number);
			}else{
				logger(7,1, request, "FALLBACK: (Gammu) Sending SMS \"%s\" to number %s failed!", sms_info.message, sms_info.phone_number);
			}
		}

		/* If the sms was not send and the fallback is at, send it via at */
		if( strcmp( inst->send_method_fallback, "at" ) == 0 && sms_sent == 0 ){
			smsret = send_sms_via_at(sms_info, inst->modem_port);
			/* Set log-messages, according to the return-value */
			if( smsret == 0 ){
				sms_sent = 1;
				logger(7, 3, request, "FALLBACK: SMS \"%s\" sent via at-commands to number %s", sms_info.message, sms_info.phone_number);
			}else{
				char *reason;
				switch(smsret){;
					case -1 : reason = "Open failed! Valid modem?"; break;
					case -2 : reason = "Could not get the current time => time() failed."; break;
					case -3 : reason = "Locking failed! Another process is using the modem? Or wrong path/file for modem?"; break;
					case -4 : reason = "AT failed! Modem down?"; break;
					case -5 : reason = "AT+CFUN failed! Modem down?"; break;
					case -6 : reason = "AT+CMGF failed! Modem down?"; break;
					case -7 : reason = "AT+CSCS failed! Modem down?"; break;
					case -8 : reason = "AT+CSMP (SMS-Class) failed! Modem down?"; break;
					case -9 : reason = "Setting number failed! Modem down?"; break;
					case -10 : reason = "Setting/Sending message failed! Modem down?"; break;
					case -11 : reason = "Unlocking the file failed!"; break;
					default : reason = "Return-Code not recognized!"; break;
				}
				logger(7 ,1, request, "FALLBACK: (AT-commands) Sending SMS \"%s\" to number %s failed! Reason: %s", sms_info.message, sms_info.phone_number, reason );
			}
		}

		/* If the sms was not send and the fallback is at, send it via mailx */
		if( strcmp( inst->send_method_fallback, "mailx" ) == 0 && sms_sent == 0 ){
			smsret = send_sms_via_mailx(sms_info, inst->send_email_gw);
			/* Set log-messages, according to the return-value */
			if( smsret == 0 ){
				sms_sent = 1;
				logger(7, 3, request, "FALLBACK: SMS \"%s\" sent via mailx to number %s", sms_info.message, sms_info.phone_number);
			}else{
				logger(7, 1, request, "FALLBACK: (Mailx) Sending SMS \"%s\" to number %s failed!", sms_info.message, sms_info.phone_number);
			}
		}
	}else{
		/* If we don't have a fallback, set a log */
		logger(7, 1, request, "No valid send_method_fallback given. Fallback-mechanism was ignored." );
		if( sms_sent == 0 ){
			/* log it! */
			logger(7, 1, request, "Since the sms was not send and we don't have a fallback, we reject the user." );
			return RLM_MODULE_NOOP;
		}
	}

	/* If sending the sms via the fallback also failed, log it, reject the user */
	if( sms_sent == 0 ){
		logger(7, 1, request, "Since the sms was not send (also tried fallback), we reject the user." );
		return RLM_MODULE_NOOP;
	}


	/* Now send the challenge-response to the VPN-Client */
	if( inst->challenge_string == NULL ){ // If we don't have a challenge-string we'll take the default!
		reply = pairmake("Reply-Message", "Enter SMS-Code!", T_OP_EQ);
		logger(7, 2, request, "There was no challenge-message provided, so we use the fallback.");
	}else{
		reply = pairmake("Reply-Message", inst->challenge_string, T_OP_EQ);
	}
	pairadd(&request->reply->vps, reply);
	state = pairmake("State", "0", T_OP_EQ);
	pairadd(&request->reply->vps, state);

	request->reply->code = PW_ACCESS_CHALLENGE;
	logger(7, 3, request, "Sending Access-Challenge for user \"%s\".", username);

	return RLM_MODULE_HANDLED;
}


/* This function checks if the username and the code which is entered
 * is the same as we created in the function smschallenge_authorize. If yes
 * the user is finally logged in, otherwise we give back he failed and he has
 * to do it again.
 */
static int smschallenge_authenticate(void *instance, REQUEST *request){
	/* MySQL Vars */
	MYSQL *conn = NULL;
	MYSQL_RES *res = NULL;
	char query[1024];
	int ret_val;

	/* loggin, and setting vars */
	int loggedin = 0;
	rlm_smschallenge_t *inst = instance;


	/* If we didn't got any configs, then we take as fallback the defaults*/
	if( inst->account_length == 0 ){
		inst->account_length = 20;
		logger(7, 2, request, "There was no account-length given. fallback 20 is used.");
	}
	if( inst->max_password_length == 0 ){
		inst->max_password_length = 12;
		logger(7, 2, request, "There was no max_password-length given. fallback 12 is used.");
	}
	if( inst->code_blocks == 0 ){
		inst->code_blocks = 2;
		logger(7, 2, request, "There was no code-blocks given. fallback 2 is used.");
	}

	/* Cutted userinputs will be saved in here */
	char username[inst->account_length];
	char code[inst->code_blocks*4+1];
	/* Prevent SQL-Injection when using following vars (will be escaped) */
	char username_escaped[(2 * strlen(username))+1];
	char code_escaped[(2 * strlen(code))+1];

	/* Prevent buffer overflow through long user/code-pw */
	if (strlen(request->username->vp_strvalue) < inst->account_length ) {
		strncpy( username, request->username->vp_strvalue, strlen(request->username->vp_strvalue) +1);
	} else {
		strncpy( username, request->username->vp_strvalue, inst->account_length);
		username[inst->account_length]='\0';
		logger(1, 0, NULL, "Username too long: truncated to %d characters", inst->account_length);
	}
	if (strlen(request->password->vp_strvalue) <= inst->code_blocks*4 ) {
		strncpy( code, request->password->vp_strvalue, strlen(request->password->vp_strvalue) +1);
	} else {
		strncpy( code, request->password->vp_strvalue, inst->code_blocks*4);
		code[inst->code_blocks*4+1]='\0';
		logger(1, 0, NULL, "Code too long: truncated to %d characters", inst->code_blocks*4);
	}



	// if set, check the prefix
	if(inst->vpn_prefix != NULL){
		int prefix_length = 0;
		char prefix[1024] = "";
		char vpnprefix[1024] = "";

		prefix_length = strlen(inst->vpn_prefix);
		strncpy(prefix, username, prefix_length);
		strncpy(vpnprefix, inst->vpn_prefix, prefix_length);

		int j = 0;
		for(j = 0; j < prefix_length; j++){
			prefix[j]=tolower(prefix[j]);
			vpnprefix[j] = tolower(vpnprefix[j]);
		}
		if( strcmp(vpnprefix, prefix) != 0 ){
			logger(7, 1, request, "VPN Prefix wrong, authentication failed user=%s", request->username->vp_strvalue);
			return RLM_MODULE_REJECT;
		}
		strcpy(username, &username[3]);
	}

	logger(5, 0, request, "Challenge with password \"%s\" and username \"%s\"", code, username);

	if((ret_val = establish_sql_connection(&conn, inst, request)) != 0){
		return ret_val;
	}

	/* Make sure no runnable mysql-query is in the username */
	mysql_real_escape_string(conn, username_escaped, username, strlen(username));
	/* And int the Password also */
	mysql_real_escape_string(conn, code_escaped, code, strlen(code));


	/* Build the query for the base-login (mysql-function) */
	sprintf( query, "SELECT code_login('%s', '%s');", username_escaped, code_escaped );

	if((ret_val = sql_select(&conn, query, &res, request)) != 0){
		return ret_val;
	}

	get_answer(&res, &loggedin);

	mysql_close(conn);

	/* If no or more users are found for this login, we have a problem */
	if( loggedin != 1 ){
		logger(7, 1, request, "Challenge failed, look for older logs, the reconstruct the error.");
		return RLM_MODULE_REJECT;
	}

	/* Just tell shortly that everything worked */
	logger(7, 3, request, "Challenge and Login successful, user=%s", username);

	return RLM_MODULE_OK;
}

/* Clean up anything, nothing special */
static int smschallenge_detach(void *instance){
	free(instance);
	return 0;
}

/* Basic inital for the module, look at the official documentation */
module_t rlm_smschallenge = {
	RLM_MODULE_INIT,
	"smschallenge",
	RLM_TYPE_THREAD_SAFE,			/* type */
	smschallenge_instantiate,		/* instantiation */
	smschallenge_detach,			/* detach */
	{
		smschallenge_authenticate,	/* authentication */
		smschallenge_authorize,		/* authorization */
		NULL,						/* preaccounting */
		NULL,						/* accounting */
		NULL,						/* checksimul */
		NULL,						/* pre-proxy */
		NULL,						/* post-proxy */
		NULL						/* post-auth */
	},
};
