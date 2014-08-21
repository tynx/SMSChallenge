/**
 * Copyright (C) 2014 Luginbühl Timon, Müller Lukas, Swisscom AG
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

#include <stdlib.h>
#include <string.h>
#include <stdarg.h>
#include <curl/curl.h>


struct soap_response{
	char	*ptr;
	size_t	len;
};

int soap_init_string(struct soap_response *s) {
	s->len = 0;
	s->ptr = malloc(s->len+1);
	if (s->ptr == NULL) {
		fprintf(stderr, "malloc() failed\n");
		return -1;
	}
	s->ptr[0] = '\0';
	return 0;
}

size_t soap_writefunc(void *ptr, size_t size, size_t nmemb, struct soap_response *s){
	size_t new_len = s->len + size*nmemb;
	s->ptr = realloc(s->ptr, new_len+1);
	if (s->ptr == NULL) {
		printf("realloc() failed in 'soap_writefunc'!");
		return 1;
	}
	memcpy(s->ptr+s->len, ptr, size*nmemb);
	s->ptr[new_len] = '\0';
	s->len = new_len;

	return size*nmemb;
}


int main(){

	int success = 0;
	CURL *curl;
	CURLcode res;
	struct soap_response response;

	char soap_url[1024];
	char soap_user[1024];
	char soap_password[1024];
	char soap_body[10240];
	char phone_number[1024];
	char message[10240];


	printf("SOAP URL:\n");
	fgets(soap_url, 1024, stdin);
	//scanf("%s", soap_url);

	printf("SOAP user:\n");
	//scanf("%s", soap_user);
	fgets(soap_user, 1024, stdin);
	int len = strlen(soap_user);
	if(soap_user[len-1] == '\n' )
		soap_user[len-1] = 0;

	printf("SOAP password:\n");
	//scanf("%s", soap_password);
	fgets(soap_password, 1024, stdin);
	len = strlen(soap_password);
	if(soap_password[len-1] == '\n' )
		soap_password[len-1] = 0;

	printf("SOAP Body: (without escaping the doublequotes!)\n");

	fgets(soap_body, 10240, stdin);
	if(strlen(soap_body) <= 1){
		fgets(soap_body, 10240, stdin);
	}

	printf("Phone Number:\n");
	//scanf("%s", phone_number);
	fgets(phone_number, 1024, stdin);

	printf("Message:\n");
	fgets(message, 10240, stdin);
	len = strlen(message);


	//printf("Variables: \n");
	//printf("url:%s \n user: %s \n pw:%s \n nmbr:%s \n soap_body:%s ! ", soap_url, soap_user, soap_password, phone_number, soap_body);


 	char authentication[1024] = "";

	sprintf(authentication, "%s:%s", soap_user, soap_password);

	char post_body[10240] = "";
	
	sprintf(post_body, soap_body, phone_number, message, "true");

	curl = curl_easy_init();
	if(curl) {
		
		soap_init_string(&response);
		curl_easy_setopt(curl, CURLOPT_URL, soap_url);
		curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, soap_writefunc);
		curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
		curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
		curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 0L);
		curl_easy_setopt(curl, CURLOPT_SSL_VERIFYHOST, 0L);
		curl_easy_setopt(curl, CURLOPT_USERPWD, authentication);
		curl_easy_setopt(curl, CURLOPT_POSTFIELDS, post_body);

		/* Perform the request, res will get the return code */ 
		res = curl_easy_perform(curl);

		/* Check for errors */ 
		if(res == CURLE_OK){
			if(strstr(response.ptr, "OK") != NULL){
				success = 1;
			}else{
				printf("\ncurl_easy_perform() failed: %s", curl_easy_strerror(res));
				printf("response.ptr:%s", response.ptr);
			}
		}else{
			printf("\ncurl_easy_perform() failed: %s", curl_easy_strerror(res));
		}

		/* always cleanup */
		free(response.ptr);
		curl_easy_cleanup(curl);
	}
	
	if(success == 1)
		printf("\nSUCCESS - SMS sent\n");
	else
		printf("\nFAIL - SMS could not be sent\n");

	return (success == 1) ? 0 : -1;

}
