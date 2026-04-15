#include "camera_system.h"
#include <time.h>
#include <Arduino.h>

const char* ntpServer = "pool.ntp.org";
const long gmtOffset_sec = -5 * 3600;
const int daylightOffset_sec = 3600;

// Initialize time via NTP - Call AFTER WiFi is connected
void initTime() {
  int retryCount = 0;

  while (retryCount < 3) {
    configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
    
    struct tm timeinfo;
    if (getLocalTime(&timeinfo) && timeinfo.tm_year > (2020 - 1900)) {
      addLog("NTP Server Connection: Successful");
      break;
    } else {
      retryCount++;
      addLog("Retrying connection ");
      addLog(String(retryCount).c_str());
      addLog("/3 times.");
      delay(2000);
    }
  }

  if (retryCount > 3) {
    addLog("NTP Server Connection: Failed\nTime will fall back to midnight.");  
  } 
}

// Get current time as struct tm
struct tm getTimeStruct() {
  struct tm timeinfo;
  if (getLocalTime(&timeinfo)) {
    return timeinfo;
  }
  memset(&timeinfo, 0, sizeof(timeinfo)); // Return zeroed struct if NTP fails
  return timeinfo;
}

// Format struct tm as "YYYY-MM-DD_HH-MM-SS"
String formatTimestamp(struct tm timeinfo) {
  char buffer[25];

  snprintf(
    buffer,
    sizeof(buffer),
    "%04d-%02d-%02d_%02d-%02d-%02d",
    timeinfo.tm_year + 1900,
    timeinfo.tm_mon + 1,
    timeinfo.tm_mday,
    timeinfo.tm_hour,
    timeinfo.tm_min,
    timeinfo.tm_sec
  );

  return String(buffer);
}