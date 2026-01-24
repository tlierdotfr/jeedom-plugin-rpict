#!/usr/bin/python3
# -*- coding: utf-8 -*-
# vim: tabstop=8 expandtab shiftwidth=4 softtabstop=4

""" Read one rpict frame and output the frame in CSV format on stdout
"""

import _thread
import argparse
import json
import os
import signal
import sys
import time
import traceback
import globals

try:
    from jeedom.jeedom import *
except ImportError as ex:
    print("Error: importing module from jeedom folder")
    print(ex)
    sys.exit(1)

import serial
from datetime import date, datetime

class error(Exception):
    def __init__(self, value):
        self.value = value
    def __str__(self):
        return repr(self.value)


# ----------------------------------------------------------------------------
# Rpict core
# ----------------------------------------------------------------------------
class Rpict:
    """ Fetch rpict datas and call user callback
    each time all data are collected
    """

    def __init__(self):
        logging.debug("RPICT------INIT CONNECTION")

    @staticmethod
    def close():
        """ close telinfo modem
        """
        logging.info("RPICT------CLOSE CONNECTION")
        if globals.RPICT_SERIAL is not None and globals.RPICT_SERIAL.is_open:
            globals.RPICT_SERIAL.close()
            logging.info("RPICT------CONNECTION CLOSED")

    def terminate(self):
        print("Terminating...")
        self.close()
        os.remove("/tmp/rpict.pid")
        sys.exit()

    def read(self):
        """ Fetch one full frame for serial port
        If some part of the frame is corrupted, it waits until the next one, so if you have corruption issue,
        this method can take time, but it enures that the frame returned is valid.
        @return frame : list of dict {name, value, checksum}
        """
        content = {}
        resp = (globals.RPICT_SERIAL.readline().decode("UTF-8"))
        
        data_temp = resp.split()
        x = 0
        content['nid'] = data_temp.pop(x)
        logging.debug('RPICT----nid : ' + content['nid'])
        for value in data_temp:
            x += 1
            name="ch" + str(x)
            content[name] = str(value)
            logging.debug('RPICT----name : ' + name + ' value : ' + str(value))
        return [content]

                        
    # noinspection PyBroadException
    def run(self):
        """ Main function
        """
        data = {}
        data_temp = {}
        raz_day = 0
        raz_time = 0
        info_heure_calcul = 0
        
        # Read a frame + RAZ au changement de date + evite le heartbeat du demon
        raz_time = datetime.now()
        raz_day = date.today()
        info_heure = datetime.now()
        while(1):
            if raz_day != date.today():
                raz_day = date.today()
                time.sleep(10)
                logging.info("RPICT------ HEARTBEAT raz le " + str(raz_day))
                for cle, valeur in list(data.items()):
                    data.pop(cle)
                    data_temp.pop(cle)
            
            frame = self.read()
            logging.debug(frame)
            _SendData = frame.pop(0)
            raz_calcul = datetime.now() - raz_time

            try:
                raz_time = datetime.now()
                _SendData["device"] = data["nid"]
                globals.JEEDOM_COM.add_changes('device::' + data["nid"], _SendData)
            except Exception:
                error_com = "Connection error"
                logging.error(error_com)
            info_heure_calcul = datetime.now() - info_heure
            if info_heure_calcul.seconds > 1800:
                logging.info('RPICT------ Dernières datas reçues de la TIC : ' + str(data))
                logging.info('RPICT------ Dernières datas envoyées vers Jeedom : ' + str(_SendData))
                info_heure = datetime.now()
            logging.debug("RPICT------ START SLEEPING " + str(globals.cycle_sommeil) + " seconds")
            time.sleep(globals.cycle_sommeil)
            logging.debug("RPICT------ WAITING : " + str(
                globals.RPICT_SERIAL.in_waiting) + " octets dans la file apres sleep ")
            if globals.RPICT_SERIAL.in_waiting > 1500:
                globals.RPICT_SERIAL.reset_input_buffer()
                logging.debug("RPICT------ BUFFER OVERFLOW => FLUSH")
                logging.debug(str(globals.RPICT_SERIAL.in_waiting) + " octets dans la file apres flush ")
        self.terminate()


# noinspection PyBroadException
def open():
    """ open rpict device
    """
    try:
        logging.info("RPICT------ OPEN CONNECTION")
        globals.RPICT_SERIAL = serial.Serial(globals.port, globals.vitesse, bytesize=7, parity='N', stopbits=1)
        logging.info("RPICT------ CONNECTION OPENED")
    except serial.SerialException:
        logging.error("RPICT------ Error opening RPICT device '%s' : %s" % (globals.port, traceback.format_exc()))


def read_socket(cycle):
    while True:
        try:
            global JEEDOM_SOCKET_MESSAGE
            if not JEEDOM_SOCKET_MESSAGE.empty():
                logging.debug("SOCKET-READ------ Message received in socket JEEDOM_SOCKET_MESSAGE")
                message = json.loads(JEEDOM_SOCKET_MESSAGE.get())
                logging.debug("SOCKET-READ------ Message received in socket JEEDOM_SOCKET_MESSAGE " + message['cmd'])
                if message['apikey'] != globals.apikey:
                    logging.error("SOCKET-READ------ Invalid apikey from socket : " + str(message))
                    return
                logging.debug('SOCKET-READ------ Received command from jeedom : ' + str(message['cmd']))
                if message['cmd'] == 'action':
                    logging.debug('SOCKET-READ------ Attempt an action on a device')
                    _thread.start_new_thread(action_handler, (message,))
                    logging.debug('SOCKET-READ------ Action Thread Launched')
                elif message['cmd'] == 'changelog':
                    log = logging.getLogger()
                    for hdlr in log.handlers[:]:
                        log.removeHandler(hdlr)
                    jeedom_utils.set_log_level('info')
                    logging.info('SOCKET-READ------ Passage des log du demon en mode ' + message['level'])
                    for hdlr in log.handlers[:]:
                        log.removeHandler(hdlr)
                    jeedom_utils.set_log_level(message['level'])
        except Exception as e:
            logging.error("SOCKET-READ------ Exception on socket : %s" % str(e))
            logging.debug(traceback.format_exc())
        time.sleep(cycle)

def listen():
    globals.PENDING_ACTION = False
    jeedom_socket.open()
    logging.info("RPICT------ Start listening...")
    globals.RPICT = Rpict()
    logging.info("RPICT------ Preparing Rpict...")
    _thread.start_new_thread(read_socket, (globals.cycle,))
    logging.debug('RPICT------ Read Socket Thread Launched')
    while 1:
        try:
            try:
                logging.info("RPICT------ RUN")
                open()
            except error as err:
                logging.error(err.value)
                globals.RPICT.terminate()
                return
            globals.RPICT.run()
        except Exception as e:
            print("Error:")
            print(e)
            shutdown()


def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()


def shutdown():
    log = logging.getLogger()
    for hdlr in log.handlers[:]:
        log.removeHandler(hdlr)
    jeedom_utils.set_log_level('debug')
    logging.info("RPICT------ Shutdown")
    logging.info("Removing PID file " + str(globals.pidfile))
    try:
        os.remove(globals.pidfile)
    except:
        pass
    try:
        jeedom_socket.close()
    except:
        pass
    logging.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)


# ------------------------------------------------------------------------------
# MAIN
# ------------------------------------------------------------------------------

parser = argparse.ArgumentParser(description='RPICT Daemon for Jeedom plugin')
parser.add_argument("--apikey", help="Value to write", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Value to write", type=str)
parser.add_argument("--socketport", help="Socket Port", type=str)
parser.add_argument("--sockethost", help="Socket Host", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--port", help="Port du modem", type=str)
parser.add_argument("--vitesse", help="Vitesse du modem", type=str)
parser.add_argument("--cyclesommeil", help="Wait time between 2 readline", type=str)
parser.add_argument("--pidfile", help="pidfile", type=str)
args = parser.parse_args()

if args.apikey:
    globals.apikey = args.apikey
if args.loglevel:
    globals.log_level = args.loglevel
if args.callback:
    globals.callback = args.callback
if args.socketport:
    globals.socketport = args.socketport
if args.sockethost:
    globals.sockethost = args.sockethost
if args.cycle:
    globals.cycle = args.cycle
if args.port:
    globals.port = args.port
if args.vitesse:
    globals.vitesse = args.vitesse
if args.cyclesommeil:
    globals.cycle_sommeil = args.cyclesommeil
if args.pidfile:
    globals.pidfile = args.pidfile


globals.socketport = int(globals.socketport)
globals.cycle = float(globals.cycle)
globals.cycle_sommeil = float(globals.cycle_sommeil)

jeedom_utils.set_log_level(globals.log_level)

globals.JEEDOM_COM = jeedom_com(apikey=globals.apikey, url=globals.callback, cycle=globals.cycle)
globals.pidfile = globals.pidfile + ".pid"
logging.info('RPICT------Start rpictd')
jeedom_utils.write_pid(str(globals.pidfile))

logging.info('RPICT------ Cycle Sommeil : ' + str(globals.cycle_sommeil))
logging.info('RPICT------ Socket port : ' + str(globals.socketport))
logging.info('RPICT------ Socket host : ' + str(globals.sockethost))
logging.info('RPICT------ Log level : ' + str(globals.log_level))
logging.info('RPICT------ Callback : ' + str(globals.callback))
logging.info('RPICT------ Vitesse : ' + str(globals.vitesse))
logging.info('RPICT------ Apikey : ' + str(globals.apikey))
logging.info('RPICT------ Cycle : ' + str(globals.cycle))
logging.info('RPICT------ Port : ' + str(globals.port))
logging.info('RPICT------ Pid File : ' + str(globals.pidfile))
signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)
if not globals.JEEDOM_COM.test():
    logging.error('RPICT------ Network communication issues. Please fix your Jeedom network configuration.')
    shutdown()
jeedom_socket = jeedom_socket(port=globals.socketport, address=globals.sockethost)
listen()
