#ifndef CAMERA_SYSTEM_H
#define CAMERA_SYSTEM_H

#include <Arduino.h>
#include "FS.h"

extern String logBuffer; //external variable to be used in each cpp file so all the logs can be added together

/**
 * function to add single messages to the log buffer
 */
void addLog(const char* message);

/**
 * function to add messages with a second or more variables to the log buffer
 */
void addLogf(const char* format, ...);

/**
 * function to add messages to log buffer with no trailing new line
 */
void addLogNoNewLine(const char* message);

/**
 * function to clear the log buffer
 */
void clearLog();

/**
 * function to save logs to sd card
 */
void saveToSDCard(fs::FS &fs, const char* path, String message);

/**
 * function to capture a photo and send it via http POST request
 * @param webhookHost URL or IP address of the webhook
 * @param webhookPort port for the webhook address
 * @param webhookPath url path for the webhook
 * @param timeStamp timestamp data
 */
void capturePhotoAndSendData(const char* webhookHost, int webhookPort, const char* webhookPath, String timeStamp);

/**
 * function to return the amount of seconds to sleep
 * @param timeinfo timestamp data
 */
int setSleepSchedule(struct tm timeinfo);

/**
 * function to connect to ntp server and initialize the time
 */
void initTime();

/**
 * function to retrieve the timestamp in tm data format
 */
struct tm getTimeStruct();

/**
 * function to format the timestamp into a string
 * @param timeinfo tm format of the timestamp
 */
String formatTimestamp(struct tm timeinfo);

/**
 * function to connect to wifi
 */
void initWiFi();

/**
 * function to initialize the camera
 */
void camera_init();

#endif