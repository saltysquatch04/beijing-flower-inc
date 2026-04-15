#include "camera_system.h"
#include <Arduino.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// Sleep until next 10-minute boundary
#define WAKE_HOUR  8   // 08:00 (8AM)
#define SLEEP_HOUR 17  // 17:00 (8PM)
#define SECONDS_IN_DAY 86400

#define ROCHESTER_LAT 43.1566 // replace with your lat
#define ROCHESTER_LNG -77.6088 // replace with your long

struct SunTimes {
  int sunriseHour;
  int sunriseMinute;
  int sunsetHour;
  int sunsetMinute;
  bool valid;
};

// fetch sunrise and sunset for sleep scheduling
SunTimes fetchSunTimes() {
  SunTimes result = {0, 0, 0, 0, false};
  HTTPClient http;
  String url = "https://api.sunrise-sunset.org/json?lat=" + String(ROCHESTER_LAT) +
               "&lng=" + String(ROCHESTER_LNG) +
               "&formatted=0&tzid=America/New_York";

  http.begin(url);
  int httpCode = http.GET();

  if (httpCode != 200) {
    Serial.printf("Sunrise API request failed, code: %d\n", httpCode);
    http.end();
    return result;
  }

  String payload = http.getString();
  http.end();

  StaticJsonDocument<1024> doc;
  DeserializationError error = deserializeJson(doc, payload);
  if (error) {
    Serial.printf("Failed to parse sunrise API response\n");
    return result;
  }

  String sunrise = doc["results"]["sunrise"].as<String>();
  String sunset  = doc["results"]["sunset"].as<String>();

  int srHour   = sunrise.substring(11, 13).toInt();
  int srMinute = sunrise.substring(14, 16).toInt();
  int ssHour   = sunset.substring(11, 13).toInt();
  int ssMinute = sunset.substring(14, 16).toInt();

  // Round to nearest 10 minutes
  auto roundTo10 = [](int &hour, int &minute) {
    int rounded = ((minute + 5) / 10) * 10;  // nearest 10
    if (rounded == 60) {
      rounded = 0;
      hour = (hour + 1) % 24;               // wrap if needed
    }
    minute = rounded;
  };

  roundTo10(srHour, srMinute);
  roundTo10(ssHour, ssMinute);

  result.sunriseHour   = (srHour + 1) % 24;
  result.sunriseMinute = srMinute;
  result.sunsetHour    = (ssHour - 1 + 24) % 24;
  result.sunsetMinute  = ssMinute;
  result.valid         = true;

  Serial.printf("Sunrise: %02d:%02d, Sunset: %02d:%02d (local, rounded)\n",
                result.sunriseHour, result.sunriseMinute,
                result.sunsetHour, result.sunsetMinute);

  return result;
}

int setSleepSchedule(struct tm timeinfo) {
  int currentSeconds = (timeinfo.tm_hour * 3600) + (timeinfo.tm_min * 60) + timeinfo.tm_sec;

  // Try to get dynamic sunrise/sunset, fall back to hardcoded if it fails
  SunTimes sun = fetchSunTimes();
  int sunriseSeconds = sun.valid ? (sun.sunriseHour * 3600) + (sun.sunriseMinute * 60) : (WAKE_HOUR  * 3600);
  int sunsetSeconds  = sun.valid ? (sun.sunsetHour  * 3600) + (sun.sunsetMinute  * 60) : (SLEEP_HOUR * 3600);

  // Nighttime: sleep until sunrise 
  if (currentSeconds >= sunsetSeconds || currentSeconds < sunriseSeconds) {
    int secondsUntilSunrise;
    if (currentSeconds >= sunsetSeconds) {
      // After sunset — sleep until tomorrow's sunrise
      secondsUntilSunrise = (SECONDS_IN_DAY - currentSeconds) + sunriseSeconds;
    } else {
      // Before sunrise — sleep until today's sunrise
      secondsUntilSunrise = sunriseSeconds - currentSeconds;
    }
    addLogf("Night mode: sleeping %d seconds until sunrise\n", secondsUntilSunrise);
    return secondsUntilSunrise;
  }

  // Daytime: align to next 10-minute boundary 
  int nextMinute = ((timeinfo.tm_min / 10) + 1) * 10;
  int sleepMinutes;
  if (nextMinute >= 60) {
    sleepMinutes = 60 - timeinfo.tm_min;
  } else {
    sleepMinutes = nextMinute - timeinfo.tm_min;
  }
  int sleepSeconds = (sleepMinutes * 60) - timeinfo.tm_sec;
  if (sleepSeconds <= 0) {
    sleepSeconds = 600;
  }
  addLogf("Sleeping %d seconds until next 10-minute boundary\n", sleepSeconds);
  return sleepSeconds;
}

