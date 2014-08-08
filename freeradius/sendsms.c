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
 
/*
 * sendsms.c
 * Sent test sms on the command line
 * .e.g
 *   ./sendsms 'sms test' NUMBER /dev/ttyS1
 *
 * compile: gcc -o sendsms sendsms.c
 */
#include <stdio.h>
#include <fcntl.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <string.h>
#include <termios.h>
#include <time.h>

struct sms_content{
	char    *phone_number;
	char    *message;
	int     sms_class;
};

/* wrapper for nanosleep */
int __nsleep(const struct timespec *req, struct timespec *rem){
	struct timespec temp_rem;
	if(nanosleep(req,rem)==-1){
		__nsleep(rem,&temp_rem);
	}
    return 1;
}
 
/* usefull interface for a better implementation of sleep*/
int msleep(unsigned long milisec){
	struct timespec req={0},rem={0};
	time_t sec=(int)(milisec/1000);
	milisec=milisec-(sec*1000);
	req.tv_sec=sec;
	req.tv_nsec=milisec*1000000L;
	__nsleep(&req,&rem);
	return 1;
}

int send_command(int fd, char* at_command, int timeout) {
	char buffer[255];	/* Input buffer */
	char *bufptr;		/* Current char in buffer */
	int nbytes;			/* Number of bytes read */
	int tries;			/* Number of tries so far */
	int ret = 0;		/* return value of write for debig purposes */
	int sent = 0;		/* If the command was sent within the 3 tries */
	char* line = NULL;	/* begining of line pointer for response parsing */
	time_t start_time;	/* dont wait for too long */

	// empty the serial-output
	nbytes = read(fd, bufptr, buffer + sizeof(buffer) - bufptr - 1);

	// set current time
	start_time = time(NULL);

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
		return (-1);
	}

	/* Give the modem some time to breath (100ms) */
	msleep(100);
	/* Try to read every 100ms until we reached the timeout */
	while(time(NULL) - start_time <= timeout){
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
				printf("[%s]\n", line);
				/* If the line equals OK or > (prombt) we're good for now */
				if ((strncmp(line, "OK", 2) == 0) || line[0] == '>' ) {
					/* Quit with success */
					return (0);
				}
			} while((line = strtok(NULL, "\n\r")));
		}
		/* Give the modem some time (100ms) */
		msleep(100);
	}
	/* If we got here, something went wrong while readin the data:
	   either we didn't recieve and OK or >, or the modem timed-out, we quit */
	return (-2);
}

int open_serial_device(char *device){
	int fd;					/* The file-handler */
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
	options.c_cflag |= CLOCAL;

	/* Set the new options */
	tcsetattr(fd, TCSANOW, &options);

	/* We're done, give back the file-handler */
	return fd;
}

int send_sms_via_at(struct sms_content sms, char *device){
	int fd;							/* File-handler of the serial connection */
	int file_locked = 0;			/* If locking of the file was successful */
	int timeout = 3;				/* The timeout for the AT-command in secs */
	int timeout_file = 3;			/* The timeout for the flock in secs */
	int fail = 0;	
	int fail_send=0;				/* return value */
	time_t start_time;				/* Dont wait for to long */
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

	at_command_class[0] = '\0';
	if( sms.sms_class == 0 ){
		strcpy( at_command_class, "AT+CSMP=17,167,0,16\r");
	}else{
		strcpy( at_command_class, "AT+CSMP=17,167,0,0\r");
	}

	/* Open the device */
	fd = open_serial_device(device);

	/* Was the opening successful? */
	if( fd == -1 ) fail = -1;

	/* Create a file-lock struct */
	struct flock fl = {F_WRLCK, SEEK_SET, 0, 0, getpid() };

	/* Set the current time */
	start_time = time(NULL);
	/* If nothing failed and the timeout isn't reached we continue to check */
	while(time(NULL) - start_time <= timeout_file && fail == 0 ){
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
		fail = -2;


	/* Send all the AT-commands */
	if (fail == 0 && (fail_send = send_command(fd, "AT\r", timeout)) != 0)
		fail = -3;
	if (fail == 0 && send_command(fd, "AT+CFUN=1\r", timeout) != 0)
		fail = -4;
	if (fail == 0 && send_command(fd, "AT+CMGF=1\r", timeout) != 0)
		fail = -5;
	if (fail == 0 && send_command(fd, at_command_class, timeout) != 0)
		fail = -6;
	if (fail == 0 && send_command(fd, at_command_number, timeout) != 0)
		fail = -7;
	if (fail == 0 && send_command(fd, at_command_message, timeout) != 0 )
		fail = -8;

	/* Change the file-lock struct, so it won't block anymore */
	fl.l_type = F_UNLCK;

	/* Set the unlocking struct */
	fcntl(fd, F_SETLK, &fl);

	/* close the file-handle */
	close(fd);

	/* Give back error-code in case of an error */
	if( fail != 0 ){
		return (fail);
	}

	/* If we got here, everything is fine */
	return (0);
}

int main(int argc, char *argv[]){
        struct sms_content sms_info;
	if (argc != 4) {
		printf("USAGE: $0 message  number device\n");
		return(-1);
	}
	if (strnlen(argv[1], 512) < 1) {
		printf("USAGE: $0 message  number device\n");
		return(-1);
	}
	if (strnlen(argv[2], 512) < 1) {
		printf("USAGE: $0 message  number device\n");
		return(-1);
	}
        sms_info.message = argv[1];
        sms_info.phone_number = argv[2];
        sms_info.sms_class = 0;     /* 0=flash, 1=normal sms */


	int ret = send_sms_via_at(sms_info, argv[3]);

	/* Possible validation of the return value... */
	switch(ret){
		case 0 : printf("\tSMS sent!\n"); break;
		case -1 : printf("\tOpen failed!!\n"); break;
		case -2 : printf("\tLocking failed!!\n"); break;
		case -3 : printf("\tAT failed!!\n"); break;
		case -4 : printf("\tAT+CFUN failed!!\n"); break;
		case -5 : printf("\tAT+CMGF failed!!\n"); break;
		case -6 : printf("\tAT+CSMP (SMS-Class) failed!!\n"); break;
		case -7 : printf("\tSetting number failed!!\n"); break;
		case -8 : printf("\tSetting/Sending message failed!!\n"); break;
		default : printf("\tReturn-Code not recognized!\n"); break;
	}

	if(ret == 0)
		return (0);
	return (-1);
}

/* Example output:
./sendsms 'sms test' NUMBER /dev/DEVICE
[AT]
[OK]
[AT+CFUN=1]
[OK]
[AT+CMGF=1]
[OK]
[AT+CSMP=17,167,0,16]
[OK]
[AT+CMGS="NUMBER"]
[> ]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[sms test"NUMBER"]
[+CMGS: 54]
[OK]
        SMS sent!

*/

