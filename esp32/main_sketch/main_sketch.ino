#include "camera_system.h"
#include <esp_sleep.h>
#include "SD.h"

// webhook settings
const char* webhookHost = "beijing-flower-n8n.webdev.gccis.rit.edu";
int webhookPort = 443;
const char* webhookPath = "";

// factor to convert uS to seconds
const uint64_t uS_TO_S_FACTOR = 1000000ULL;

void setup() {
    Serial.begin(115200);

    // connects to WiFi
    initWiFi();
    camera_init();
    delay(1500);
}

void loop() {
    // initializes the time and grabs current timestamp
    initTime();
    struct tm timeInfo = getTimeStruct();
    String timeStamp = formatTimestamp(timeInfo);

    addLog(("Current time: " + timeStamp).c_str());

    // captures a photo and sends it to webhook via http POST request
    capturePhotoAndSendData(webhookHost, webhookPort, webhookPath, timeStamp);

    // get another timestamp for sleep schedule calculation
    struct tm sleepTimeInfo = getTimeStruct();

    // calculates the amount of time to sleep and then instructs the esp32 to sleep for that amount of time
    int sleepSecondsLog = setSleepSchedule(sleepTimeInfo);

    char fileName[50];
    sprintf(fileName, "/%s.txt", timeStamp.c_str());
    if (!SD.begin(21)) {
        addLog("SD mount failed - card not supported or not connected");
    }
    saveToSDCard(SD, fileName, logBuffer);

    clearLog();

    int sleepSeconds = setSleepSchedule(sleepTimeInfo);

    esp_sleep_enable_timer_wakeup((uint64_t)sleepSeconds * uS_TO_S_FACTOR); // Configures the ESP32’s timer wakeup
    Serial.flush();
    esp_deep_sleep_start();
}