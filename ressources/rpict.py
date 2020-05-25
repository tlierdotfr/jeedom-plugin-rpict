#!/usr/bin/python
# -*- coding: utf-8 -*-
# vim: tabstop=8 expandtab shiftwidth=4 softtabstop=4

""" Read one rpict frame and output the frame in CSV format on stdout
"""

import serial
import os
import time
import traceback
import logging
import sys
from optparse import OptionParser
from datetime import datetime
import subprocess
import urllib2
import threading
import signal

# Default log level
gLogLevel = logging.INFO

# Device name
global_device_name = '/dev/ttyAMA0'
# Default output is stdout
global_output = sys.__stdout__
global_api = ''
global_real_path = ''
global_vitesse = '38400'
global_can_start = 'true'
global_logfile = os.path.dirname(os.path.realpath(__file__)) + '/../../../log/rpict_deamon'
global_sleep = 0
# ----------------------------------------------------------------------------
# LOGGING
# ----------------------------------------------------------------------------
class MyLogger:
    """ Our own logger """
    def __init__(self):
        self._logger = logging.getLogger('rpict')
        hdlr = logging.FileHandler(global_logfile)
        formatter = logging.Formatter('%(asctime)s %(levelname)s %(message)s')
        hdlr.setFormatter(formatter)
        self._logger.addHandler(hdlr)
        self._logger.setLevel(gLogLevel)
        self.info("logger started in " + logging.getLevelName(self._logger.getEffectiveLevel()) + " mode")

    def debug(self, text):
        try:
            self._logger.debug(text)
        except NameError:
            pass

    def info(self, text):
        try:
            text = text.replace("'", "")
            self._logger.info(text)
        except NameError:
            pass

    def warning(self, text):
        try:
            text = text.replace("'", "")
            self._logger.warn(text)
        except NameError:
            pass

    def error(self, text):
        try:
            text = text.replace("'", "")
            self._logger.error(text)
        except NameError:
            pass


# ----------------------------------------------------------------------------
# Exception
# ----------------------------------------------------------------------------
class RpictException(Exception):
    """
    Rpict exception
    """

    def __init__(self, value):
        Exception.__init__(self)
        self.value = value

    def __str__(self):
        return repr(self.value)


# ----------------------------------------------------------------------------
# Rpict core
# ----------------------------------------------------------------------------
class Rpict:
    """ Fetch rpictrmation datas and call user callback
    each time all data are collected
    """

    def __init__(self, device, cleapi, realpath, vitesse):
        """ @param device : rpict device path
        @param log : log instance
        @param callback : method to call each time all data are collected
        The datas will be passed using a dictionnary
        """
        self._log = MyLogger()
        self._device = device
        self._cleApi = cleapi
        self._realpath = realpath
        self._vitesse = vitesse
        self._ser = None

    def open(self):
        """ open rpict device
        """
        try:
            self._log.info("Try to open Rpict link '%s' with speed '%s'" % (self._device, self._vitesse))
            #self._ser = serial.Serial(self._device, self._vitesse, bytesize=7, parity='E', stopbits=1)
            self._ser = serial.Serial(self._device, self._vitesse, bytesize=7, parity='N', stopbits=1)
            self._log.info("Rpict link successfully opened")
        except:
            error = "Error opening Rpict link '%s' : %s" % (self._device, traceback.format_exc())
            #self._log.error(error)
            raise RpictException(error)

    def close(self):
        """ close rpict link
        """
        self._log.info("Try to close Rpict link")
        if self._ser != None  and self._ser.isOpen():
            self._ser.close()
            self._log.info("Rpict link successfully closed")

    def terminate(self):
        print "Terminating..."
        self.close()
        os.remove("/tmp/rpict.pid")
        sys.exit()

    def run(self):
        """ Main function
        """
        print "Starting deamon..."
        data = {}
        data_temp = {}
        separateur = " "
        send_data = ""

        def target():
            self.process = None
            self.process = subprocess.Popen(self.cmd + send_data_bak, shell=True)
            self.process.communicate()
            self.timer.cancel()

        def timer_callback():
            #logger.debug("Thread timeout, terminate it")
            if self.process.poll() is None:
                try:
                    self.process.kill()
                except OSError as error:
                    #logger.error("Error: %s " % error)
                    self._log.error("Error: %s " % error)
                self._log.warning("Thread terminated")
            else:
                self._log.warning("Thread not alive")

        # Open Rpict link
        try:
            self.open()
        except RpictException as err:
            print "Error opening serial"
            self._log.error(err.value)
            self.terminate()
            return
        # Read a frame
        while(1):
            send_data = ""
            frame = self._ser.readline()
            self._log.debug(frame)
            
            data_temp = frame.split()
            x = 0
            data['nid'] = data_temp.pop(x)
            for value in data_temp:
                x += 1
                cle="ch" + str(x)
                data[cle] = str(value)
            #print(data)

            self.cmd = 'nice -n 19 timeout 8 /usr/bin/php ' + self._realpath + '/../php/jeeRpict.php api=' + self._cleApi
            separateur = " "


            for cle, valeur in data.items():
                send_data += separateur + cle +'='+ valeur

            try:
                if frame != "":
                    try:
                        self._log.debug(self.cmd + send_data)
                        send_data_bak = send_data
                        thread = threading.Thread(target=target)
                        self.timer = threading.Timer(int(10), timer_callback)
                        self.timer.start()
                        thread.start()
                    except Exception, e:
                        errorCom = "Connection error '%s'" % e
                if (global_sleep != 0):
                        self._log.debug("start sleeping " + str(global_sleep) + " seconds")
                        time.sleep(global_sleep)
                        self._log.debug("octets dans la file apres sleep " + str(self._ser.inWaiting()))
                        self._ser.flushInput()
                        self._log.debug("octets dans la file apres flush " + str(self._ser.inWaiting()))
            except Exception:
                erreur = ""
        self.terminate()

    def exit_handler(self, *args):
        self.terminate()
        self._log.info("[exit_handler]")

#------------------------------------------------------------------------------
# MAIN
#------------------------------------------------------------------------------
if __name__ == "__main__":
    usage = "usage: %prog [options]"
    parser = OptionParser(usage)
    parser.add_option("-p", "--port", dest="port", help="port du rpict")
    parser.add_option("-c", "--cleapi", dest="cleapi", help="cle api de jeedom")
    parser.add_option("-d", "--debug", dest="debug", help="mode debug")
    parser.add_option("-r", "--realpath", dest="realpath", help="path usr")
    parser.add_option("-v", "--vitesse", dest="vitesse", help="vitesse du lien uart")
    parser.add_option("--logpath", dest="logpath", help="emplacement du log")
    parser.add_option("--sleep", dest="sleep", help="sleep time", type="int")

    (options, args) = parser.parse_args()
    if options.port:
        try:
            global_device_name = options.port
        except:
            error = "Can not change port %s" % options.port
            raise RpictException(error)
    if options.debug:
        try:
            if options.debug == '1':
                gLogLevel = logging.DEBUG
        except:
            error = "Can not set debug mode %s" % options.debug
            #raise RpictException(error)
    if options.cleapi:
        try:
            global_api = options.cleapi
        except:
            error = "No API key passed %s" % options.cleapi
            raise RpictException(error)
    if options.realpath:
        try:
            global_real_path = options.realpath
        except:
            error = "Can not get realpath %s" % options.realpath
            raise RpictException(error)
    if options.vitesse:
        try:
            global_vitesse = options.vitesse
        except:
            error = "Can not get vitesse %s" % options.vitesse
            raise RpictException(error)
    if options.logpath:
        try:
            global_logfile = os.path.realpath(options.logpath) + '/rpict_deamon'
            print "loggile : " + global_logfile
        except:
            error = "Can not get logptah %s" % options.logpath
            raise RpictException(error)
    if options.sleep:
        try:
            global_sleep = options.sleep
        except:
            error = "Can not get sleep %s" % options.sleep
            raise RpictException(error)
    if global_can_start == 'true':
        print "Init deamon ..."
        pid = str(os.getpid())
        file("/tmp/rpict.pid", 'w').write("%s\n" % pid)
        print "pidfile : " + "/tmp/rpict.pid"
        rpict = Rpict(global_device_name, global_api, global_real_path, global_vitesse)
        signal.signal(signal.SIGTERM, rpict.exit_handler)
        signal.signal(signal.SIGINT, rpict.exit_handler)
        rpict.run()
    sys.exit()
