/**
 * Device Fingerprint Utility
 * 
 * Generates a device fingerprint matching the backend's DeviceService::generateFingerprint() logic.
 * The fingerprint is a SHA-256 hash of: userAgent | acceptLanguage | screenResolution | timezone | canvasFingerprint
 */

const STORAGE_KEY = 'device_fingerprint'
const CANVAS_STORAGE_KEY = 'device_canvas_fp'

/**
 * Generate a canvas fingerprint for device identification.
 */
function generateCanvasFingerprint() {
  try {
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    if (!ctx) return ''

    canvas.width = 200
    canvas.height = 50

    ctx.textBaseline = 'top'
    ctx.font = '14px Arial'
    ctx.fillStyle = '#f60'
    ctx.fillRect(125, 1, 62, 20)
    ctx.fillStyle = '#069'
    ctx.fillText('fingerprint', 2, 15)
    ctx.fillStyle = 'rgba(102, 204, 0, 0.7)'
    ctx.fillText('fingerprint', 4, 17)

    return canvas.toDataURL()
  } catch (e) {
    return ''
  }
}

/**
 * Hash a string using SHA-256.
 */
async function sha256(message) {
  const msgBuffer = new TextEncoder().encode(message)
  const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer)
  const hashArray = Array.from(new Uint8Array(hashBuffer))
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
}

/**
 * Get screen resolution string.
 */
function getScreenResolution() {
  return `${window.screen.width}x${window.screen.height}`
}

/**
 * Get timezone string.
 */
function getTimezone() {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || ''
}

/**
 * Get cached canvas fingerprint or generate a new one.
 */
function getCanvasFingerprint() {
  let canvasFp = localStorage.getItem(CANVAS_STORAGE_KEY)
  if (!canvasFp) {
    canvasFp = generateCanvasFingerprint()
    if (canvasFp) {
      localStorage.setItem(CANVAS_STORAGE_KEY, canvasFp)
    }
  }
  return canvasFp
}

/**
 * Get the device fingerprint data needed for the registration API.
 * Returns the individual fields that the backend needs.
 */
export function getDeviceData() {
  return {
    screen_resolution: getScreenResolution(),
    timezone: getTimezone(),
    canvas_fingerprint: getCanvasFingerprint(),
  }
}

/**
 * Generate the device fingerprint hash.
 * This matches the backend's DeviceService::generateFingerprint() which hashes:
 * userAgent | acceptLanguage | screenResolution | timezone | canvasFingerprint
 * 
 * Note: acceptLanguage is sent server-side from the request header, 
 * so we use navigator.language as a close approximation.
 */
export async function getDeviceFingerprint() {
  // Check cache first
  const cached = localStorage.getItem(STORAGE_KEY)
  if (cached) return cached

  const components = [
    navigator.userAgent || '',
    navigator.language || '',
    getScreenResolution(),
    getTimezone(),
    getCanvasFingerprint(),
  ]

  const data = components.join('|')
  const fingerprint = await sha256(data)

  localStorage.setItem(STORAGE_KEY, fingerprint)
  return fingerprint
}

/**
 * Clear the cached fingerprint (useful for testing or when device changes).
 */
export function clearDeviceFingerprint() {
  localStorage.removeItem(STORAGE_KEY)
  localStorage.removeItem(CANVAS_STORAGE_KEY)
}

/**
 * Get a human-readable device name.
 */
export function getDeviceName() {
  const ua = navigator.userAgent
  let platform = 'Unknown'
  let browser = 'Unknown'

  if (/Windows NT/i.test(ua)) platform = 'Windows'
  else if (/Macintosh/i.test(ua)) platform = 'macOS'
  else if (/iPhone|iPad|iPod/i.test(ua)) platform = 'iOS'
  else if (/Android/i.test(ua)) platform = 'Android'
  else if (/Linux/i.test(ua)) platform = 'Linux'

  if (/Edg\//i.test(ua)) browser = 'Edge'
  else if (/OPR\//i.test(ua)) browser = 'Opera'
  else if (/Chrome\/[\d.]+/i.test(ua)) browser = 'Chrome'
  else if (/Firefox\/[\d.]+/i.test(ua)) browser = 'Firefox'
  else if (/Safari\/[\d.]+/i.test(ua)) browser = 'Safari'

  return `${platform} - ${browser}`
}
