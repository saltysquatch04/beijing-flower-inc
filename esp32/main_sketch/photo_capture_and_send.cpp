#include "camera_system.h"
#include <esp_camera.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>

/**
 * captures photo and sends it via http request
 * along with timestamp data
 * @param webhookHost Server hostname or IP address
 * @param webhookPort Server port
 * @param webhookPath URL path for POST request
 * @param timeStamp Timestamp string for the photo
*/
void capturePhotoAndSendData(const char* webhookHost, int webhookPort, const char* webhookPath, String timeStamp) {
    // obtain pointer to a frame buffer
    camera_fb_t *fb = esp_camera_fb_get();

    // retry mechanism to ensure photo capture
    for (int i = 0; i < 3 && !fb; i++) {
        fb = esp_camera_fb_get();
        if (!fb) {
            addLog("Capture attempt failed, retrying...");
            delay(200);
        }
    }   
    // check if photo was captured
    // if not exit function
    if (!fb) {
        addLog("Camera capture failed after retries");
        return;
    }
    
    addLogNoNewLine("Image size: ");
    addLogNoNewLine(String(fb->len).c_str());
    addLog(" bytes");
    
    // create wifi client class and connect to webhook
    // if failure, release buffer
    WiFiClientSecure client;
    client.setInsecure();
    client.setTimeout(30000);
    
    if (!client.connect(webhookHost, webhookPort)) {
        addLog("Connection to server failed");
        esp_camera_fb_return(fb);
        return;
    }
    
    addLog("Connected to server");
    addLogNoNewLine("Connection status: ");
    addLog(client.connected() ? "CONNECTED" : "DISCONNECTED");
    
    // multipart body boundry
    String boundary = "----------------ESP32Boundary1234";
    
    // text part (timestamp)
    String timestampText =
        "--" + boundary + "\r\n"
        "Content-Disposition: form-data; name=\"timestamp\"\r\n\r\n" +
        timeStamp + "\r\n";
    
    // file part (image)
    String imageHeader =
        "--" + boundary + "\r\n"
        "Content-Disposition: form-data; name=\"image\"; filename=\"photo.jpg\"\r\n"
        "Content-Type: image/jpeg\r\n\r\n";
    
    String closing = "\r\n--" + boundary + "--\r\n";
    
    // sum of all data to send
    uint32_t contentLength =
        timestampText.length() + 
        imageHeader.length() +
        fb->len +
        closing.length();
    
    addLogNoNewLine("Total content length: ");
    addLog(String(contentLength).c_str());
    
    // required http headers
    size_t headersSent = 0;
    headersSent += client.print(String("POST ") + webhookPath + " HTTP/1.1\r\n");
    headersSent += client.print(String("Host: ") + webhookHost + "\r\n");
    headersSent += client.print("Content-Type: multipart/form-data; boundary=" + boundary + "\r\n");
    headersSent += client.print("Content-Length: " + String(contentLength) + "\r\n");
    headersSent += client.print("Connection: close\r\n\r\n");
    
    addLogNoNewLine("Headers sent: ");
    addLogNoNewLine(String(headersSent).c_str());
    addLog(" bytes");
    addLogNoNewLine("Connection status after headers: ");
    addLog(client.connected() ? "CONNECTED" : "DISCONNECTED");
    
    //  body: text field
    size_t textSent = 0;
    textSent += client.print(timestampText);
    
    addLogNoNewLine("Text fields sent: ");
    addLogNoNewLine(String(textSent).c_str());
    addLog(" bytes");
    addLogNoNewLine("Connection status after text: ");
    addLog(client.connected() ? "CONNECTED" : "DISCONNECTED");
    
    // body: image header 
    size_t imgHeaderSent = client.print(imageHeader);

    // verbose logging of the image header sent
    addLogNoNewLine("Image header sent: ");
    addLogNoNewLine(String(imgHeaderSent).c_str());
    addLog(" bytes");
    
    // body: image binary
    uint8_t *fbBuf = fb->buf;
    size_t fbLen   = fb->len;
    
    /**
     * @var chunkSize | sets the byte increment in which data transmitted
     * @var totalSent | keeps track of the total bytes sent to the webhook
     * @var n | used to keep track of bytes sent for transmission loop
     * @var retryCount | keeps track of the retries attempted
     * @var maxRetries | sets the max amount of retries allowed
     */
    const size_t chunkSize = 512; 
    size_t totalSent = 0;
    size_t n = 0;
    int retryCount = 0;
    const int maxRetries = 3;
    
    addLog("Starting image transmission...");
    
    while (n < fbLen) {
        // check if still connected and if not break out of transmission
        if (!client.connected()) {
            addLogNoNewLine("CONNECTION LOST at byte ");
            addLog(String(n).c_str());
            break;
        }
        
        size_t remaining = fbLen - n;

        // if the chunk size and total packet length is less than the buffer length then set the bytes to write to the chunksize
        // else set the bytes to write as the remaining bytes in the buffer length
        size_t toWrite;
        if (n + chunkSize <= fbLen) {
            toWrite = chunkSize;
        } else {
            toWrite = fbLen - n;
        }
        
        // tracking the total amount of bytes sent to the webhook
        size_t written = client.write(fbBuf + n, toWrite);
        
        // if there were bytes sent to the webhook increase n by the amount of bytes written and log the progress
        // else increase the retry counter and pause until trying transmission again. Break if more than 3 retries are attempted
        if (written > 0) {
            totalSent += written;
            n += written;
            retryCount = 0; // resets the retry counter if the transmission is successful
            
            // progress logged every 10KB
            if (totalSent % 10240 < written || n >= fbLen) {
                addLogNoNewLine("Progress: ");
                addLogNoNewLine(String(totalSent).c_str());
                addLogNoNewLine(" / ");
                addLogNoNewLine(String(fbLen).c_str());
                addLogNoNewLine(" bytes (");
                addLogNoNewLine(String((totalSent * 100) / fbLen).c_str());
                addLog("%)");
            }
            
            // short delay every 5KB
            if (totalSent % 5120 < written) {
                delay(50);
            }
        } else {
            retryCount++;
            addLogNoNewLine("Write failed at offset ");
            addLogNoNewLine(String(n).c_str());
            addLogNoNewLine(" (retry ");
            addLogNoNewLine(String(retryCount).c_str());
            addLogNoNewLine("/");
            addLogNoNewLine(String(maxRetries).c_str());
            addLog(")");
            
            if (retryCount >= maxRetries) {
                addLog("Max retries reached, aborting");
                break;
            }
            
            delay(100);
        }
    }
    
    addLogNoNewLine("Total image bytes sent: ");
    addLogNoNewLine(String(totalSent).c_str());
    addLogNoNewLine(" / ");
    addLog(String(fbLen).c_str());
    addLogNoNewLine("Connection status after image: ");
    addLogNoNewLine(client.connected() ? "CONNECTED" : "DISCONNECTED");
    
    // closing boundary 
    size_t closingSent = client.print(closing);
    addLogNoNewLine("Closing boundary sent: ");
    addLogNoNewLine(String(closingSent).c_str());
    addLog(" bytes");
    
    // flush any remaining data
    client.flush();
    addLog("Flushed client buffer");
    
    addLogNoNewLine("Connection status after flush: ");
    addLog(client.connected() ? "CONNECTED" : "DISCONNECTED");
    
    addLog("Waiting for server response...");
    
    /**
     * @var timeout | gets the current time in ms since the device has been up
     * @var gotResponse | boolean value to track status of webhook respose
     */
    unsigned long timeout = millis();
    bool gotResponse = false;
    
    // wait for a response for 15 seconds and update response status
    while (millis() - timeout < 15000) { 
        if (client.available()) {
            gotResponse = true;
            break;
        }
        if (!client.connected()) {
            addLog("Connection closed by server before response");
            break;
        }
        delay(100);
    }
    
    // print response or indicate no response from server
    if (gotResponse) {
        addLog("=== Server Response ===");
        while (client.available()) {
            String line = client.readStringUntil('\n');
            addLog(line.c_str());
        }
    } else {
        addLog("No response from server (timeout or connection closed)");
    }
    
    // releasing buffer
    esp_camera_fb_return(fb);
    
    // close webhook connection
    client.stop();
    
    addLog("Connection closed\n");
}