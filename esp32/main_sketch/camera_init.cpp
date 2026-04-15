// Make sure to enable PSRAM in our esp32 board settings for the memory allocation to work

#include "camera_system.h"
#include "esp_camera.h"
#include "Arduino.h"
#include "ESP32_OV5640_AF.h"
#include <HTTPClient.h>
#include <ArduinoJson.h>

// --- Define your camera pins here (Standard S3 pins vary by board) ---
#define PWDN_GPIO_NUM     -1
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM     10
#define SIOD_GPIO_NUM     40
#define SIOC_GPIO_NUM     39
#define Y9_GPIO_NUM       48
#define Y8_GPIO_NUM       11
#define Y7_GPIO_NUM       12
#define Y6_GPIO_NUM       14
#define Y5_GPIO_NUM       16
#define Y4_GPIO_NUM       18
#define Y3_GPIO_NUM       17
#define Y2_GPIO_NUM       15
#define VSYNC_GPIO_NUM    38
#define HREF_GPIO_NUM     47
#define PCLK_GPIO_NUM     13

// rochester coordinates
#define ROCHESTER_LAT 43.1566 // replace with your lat
#define ROCHESTER_LNG -77.6088 // replace with your long

// data struct for weather data
struct weatherData {
  int  cloudCover;
  bool valid;
};

weatherData fetchWeatherData () {
  weatherData result = {0, false};
  HTTPClient http;

  String url = "https://api.open-meteo.com/v1/forecast"
               "?latitude=" + String(ROCHESTER_LAT) +
               "&longitude=" + String(ROCHESTER_LNG) +
               "&current=cloud_cover"
               "&timezone=America%2FNew_York"
               "&forecast_days=1";

  http.begin(url);
  int httpCode = http.GET();

  if (httpCode != 200) {
    addLogf("Weather API failed, code: %d", httpCode);
    http.end();
    return result;
  }

  String payload = http.getString();
  http.end();

  StaticJsonDocument<1024> doc;
  if (deserializeJson(doc, payload)) {
    addLog("Failed to parse weather response");
  }

  result.cloudCover = doc["current"]["cloud_cover"].as<int>();
  result.valid = true;
  
  addLogf("Cloud cover: %d%%", result.cloudCover);
  return result;
}

OV5640 ov5640 = OV5640();

void camera_init() {
  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.frame_size = FRAMESIZE_UXGA; // Higher res needs PSRAM
  config.pixel_format = PIXFORMAT_JPEG; 
  config.grab_mode = CAMERA_GRAB_WHEN_EMPTY;
  config.fb_location = CAMERA_FB_IN_PSRAM;
  config.jpeg_quality = 10; // 0-63 lower means better quality but larger size
  config.fb_count = 1;

  weatherData weather = fetchWeatherData();
  int aeLevel;
  if (weather.valid) {
    if (weather.cloudCover <= 20) {
      aeLevel = -2;
    } else if (weather.cloudCover <= 40) {
      aeLevel = -1;
    } else if (weather.cloudCover <= 60) {
      aeLevel = 0;
    } else if (weather.cloudCover <= 80) {
      aeLevel = 1;
    } else {
      aeLevel = 2;
    }
  } else {
    aeLevel = 0;
  }

  // Initialize Camera
  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    addLogf("Camera init failed with error 0x%x", err);
    return;
  }

  addLog("Camera initialized successfully."); // Debug message

  // Get the sensor object to configure additional settings
  sensor_t * s = esp_camera_sensor_get();

  if (!s) {
    addLog("Failed to get camera sensor.");
    return;
  }

  // --- Orientation and Flip ---
  s->set_vflip(s, 1); // Vertical flip
  s->set_hmirror(s, 0); // Horizontal mirror

  // --- Brightness / Contrast / Saturation ---
  s->set_brightness(s, 0); // -2 to 2
  s->set_contrast(s, 1); // -2 to 2
  s->set_saturation(s, 1); // -2 to 2
  s->set_sharpness(s, 1); // -2 to 2

  // --- White Balance ---
  s->set_whitebal(s, 1); // Auto white balance
  s->set_awb_gain(s, 1);
  s->set_wb_mode(s, 0); // 0 = Auto

  // --- Exposure ---
  s->set_exposure_ctrl(s, 1); /**** Auto exposure (old setting) ****/
  // s->set_exposure_ctrl(s, 0); // Disable auto exposure for manual control
  // s->set_aec_value(s, 300); // Manual exposure (only if auto off)


  s->set_aec2(s, 1); /*** Advanced AEC (old setting) ***/
  // s->set_aec2(s, 0); // Disable advanced AEC for more manual control

  // s->set_ae_level(s, 0); /*** -2 to 2 (old setting) ***/

  s->set_ae_level(s, aeLevel); // -2 to 2 (lower means darker, attempt to reduce overexposure in bright conditions)

  // --- Gain ---
  s->set_gain_ctrl(s, 1); // Auto gain
  s->set_agc_gain(s, 0); // Manual gain if auto disabled
  
    // s->set_gainceiling(s, (gainceiling_t)6); //*** Limit noise (old setting) ***/
  s->set_gainceiling(s, (gainceiling_t)2); // Limit noise (lower means less gain but cleaner image)

  // --- Image Processing ---
  s->set_raw_gma(s, 1);        // Gamma correction
  s->set_lenc(s, 1);           // Lens shading correction
  s->set_special_effect(s, 0); // No effect

  addLog("Camera parameters configured.");

  // initialize autofocus
  ov5640.start(s);

  if (ov5640.focusInit() == 0) {
    addLog("AF firmware loaded successfully");
  } else {
    addLog("AF firmware failed to load");
  }

  delay(100); // Short delay to ensure AF is ready
  ov5640.autoFocusMode(); // single focus before photo capture
  delay(100); // Short delay to allow focus to settle

  addLog("Camera ready for capture.");
}