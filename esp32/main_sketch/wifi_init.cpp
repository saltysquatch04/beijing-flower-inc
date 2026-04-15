#include "camera_system.h"
#include <WiFi.h>

// Replace with your network credentials
const char* ssid = "";
// add const char* passwd = ""; if needed

void initWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid); // add passwd input if needed
  addLogNoNewLine("Connecting to WiFi ..");
  while (WiFi.status() != WL_CONNECTED) {
    addLogNoNewLine(String('.').c_str());
    delay(1000);
  }
  addLog("");
  addLogf("WiFi IP: %s", WiFi.localIP().toString().c_str());
}