/**
 * Weather Widget Logic
 */
async function initWeather() {
    const locEl = document.getElementById("weather-location");
    const tempEl = document.getElementById("weather-temp");
    const statusEl = document.getElementById("weather-status");

    if (!locEl || !tempEl || !statusEl) return;

    // 1. Set Defaults (Rochester, NY)
    let lat = 43.1566;
    let lon = -77.6088;
    let city = "Rochester";

    try {
        // 2. Try to get Location, but wrap it so it doesn't kill the whole process if blocked
        try {
            const geoRes = await fetch('https://ipinfo.io/json');
            if (geoRes.ok) {
                const geoData = await geoRes.json();
                const coords = geoData.loc.split(',');
                lat = coords[0];
                lon = coords[1];
                city = geoData.city;
            }
        } catch (geoError) {
            console.warn("Geo-lookup blocked or failed, using Rochester defaults.");
        }

        // 3. Get Weather from Open-Meteo (Usually not blocked as it's a data API, not a tracker)
        const weatherUrl = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&temperature_unit=fahrenheit`;
        const weatherRes = await fetch(weatherUrl);
        const weatherData = await weatherRes.json();
        
        const { temperature, weathercode, is_day } = weatherData.current_weather;

        // 4. Update UI
        locEl.textContent = city; 
        tempEl.textContent = Math.round(temperature) + "°F";
        statusEl.textContent = interpretWeatherCode(weathercode, is_day);

    } catch (error) {
        console.error("Weather API Error:", error);
        locEl.textContent = "Rochester"; 
        tempEl.textContent = "--°F";
        statusEl.textContent = "Weather Unavailable";
    }
}

/**
 * Helper to convert WMO codes to words
 */
function interpretWeatherCode(code, isDay) {
    if (isDay === 0) {
        if (code === 0) return "Clear Night";
        if (code === 1 || code === 2) return "Partly Cloudy Night";
    }

    const mapping = {
        0: "Sunny",
        1: "Mainly Clear", 2: "Partly Cloudy", 3: "Overcast",
        45: "Foggy", 48: "Foggy",
        51: "Drizzle", 53: "Drizzle", 55: "Drizzle",
        61: "Slight Rain", 63: "Rainy", 65: "Heavy Rain",
        71: "Snowy", 73: "Snowy", 75: "Heavy Snow",
        80: "Rain Showers", 81: "Rain Showers", 82: "Violent Showers",
        95: "Thunderstorm", 96: "Thunderstorm", 99: "Thunderstorm"
    };
    return mapping[code] || "Cloudy";
}

/**
 * Initialize all components
 */
document.addEventListener("DOMContentLoaded", () => {
    initWeather();
    
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const toggle = dropdown.querySelector('.mail-toggle');
        if (!toggle) return;
        dropdown.addEventListener('shown.bs.dropdown', () => toggle.classList.add('open'));
        dropdown.addEventListener('hidden.bs.dropdown', () => toggle.classList.remove('open'));
    });
});