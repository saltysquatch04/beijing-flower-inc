#include "camera_system.h"
#include <Arduino.h>
#include <stdarg.h>
#include "FS.h"

String logBuffer = "";

void addLog(const char* message) {
    Serial.println(message);
    logBuffer += String(message) + "\n";
}

void addLogNoNewLine(const char* message) {
    Serial.print(message);
    logBuffer += String(message);
}

void addLogf(const char* format, ...) {
    char buf[256];
    va_list args;
    va_start(args, format);
    vsnprintf(buf, sizeof(buf), format, args);
    va_end(args);
    Serial.println(buf);
    logBuffer += String(buf) + "\n";
}

void clearLog() {
    logBuffer = "";
}

void saveToSDCard(fs::FS &fs, const char* path, String message) {
    addLogNoNewLine("Attempting to open: ");
    addLog(path);
    
    File file = fs.open(path, FILE_WRITE);
    if (!file) {
        addLog("Failed to open file for writing");
        return;
    }
    addLog("File opened successfully");
    
    size_t written = file.print(message);
    addLogNoNewLine("Bytes written: ");
    addLog(String(written).c_str());
    
    file.close();
    addLog("File closed");
}