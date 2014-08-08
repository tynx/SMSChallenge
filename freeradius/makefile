VERS =	2.1.8
# Ubuntu 10.04:
ETCPATH = /etc/freeradius/
LIBPATH = /usr/lib/freeradius/

# If compiling from sources:
#ETCPATH = /opt/freeradius-server-2.1.9/etc/raddb/
#LIBPATH = /opt/freeradius-server-2.1.9/lib/

CONFFILE = $(ETCPATH)modules/smschallenge

# If compiling from sources:
#CFLAGS = -Wall -I/usr/include -I/opt/freeradius-server-2.1.9/include/ -DNDEBUG
# For Ubuntu 8.04 with self compiled Freeradius 2.1.9, enable -DNDEBUG
#     do not use it for Ubuntu 10.04 with the standard freeradius package
#CFLAGS = -Wall -I/usr/include  -DNDEBUG
CFLAGS = -Wall -I/usr/include 

LIBS =	-lc -L /usr/lib/mysql/ -l mysqlclient -l curl

ALL:	rlm_smschallenge.o rlm_smschallenge-$(VERS).so
	if [ ! -e ./smschallenge ]; then cp smschallenge.sample smschallenge ; fi

rlm_smschallenge.o:	rlm_smschallenge.c
	cc -g -fPIC -DPIC -c $(CFLAGS) rlm_smschallenge.c
rlm_smschallenge-$(VERS).so:	rlm_smschallenge.o
	cc -g -shared -Wl,-soname,rlm_smschallenge-$(VERS).so -o rlm_smschallenge-$(VERS).so rlm_smschallenge.o $(LIBS)
install:	ALL
	if [ ! -e $(CONFFILE) ]; then install smschallenge $(ETCPATH)modules/ ; fi
	install rlm_smschallenge-$(VERS).so $(LIBPATH)
	ln -fs rlm_smschallenge-$(VERS).so $(LIBPATH)rlm_smschallenge.so
clean: 
	rm *.o rlm_smschallenge*.so smschallenge sendsms sendsms_soap
sendsms:
	cc -o sendsms sendsms.c
sendsms_soap:
	cc -o sendsms_soap sendsms_soap.c $(LIBS)
