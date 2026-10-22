import jsQR from 'jsqr'
import { onBeforeUnmount, ref } from 'vue'

export const BOOKING_QR_PREFIX = 'AURA_BOOKING_'
// The camera sees the same code for many frames; hand it over once per hold.
const REPEAT_MS = 3000

/**
 * BR-124, D7: reads the customer's booking QR (AURA_BOOKING_<code>) from the camera and hands each code over at once.
 * Any other QR, such as a payment QR, is reported as not a booking code and never sent.
 */
export function useBookingQrScanner(onCode: (code: string) => void) {
  const scanning = ref(false)
  const error = ref<'camera' | 'not-booking' | ''>('')
  let stream: MediaStream | null = null
  let frame = 0
  let last = { value: '', at: 0 }

  function read(video: HTMLVideoElement, canvas: HTMLCanvasElement) {
    if (!scanning.value) return
    const context = canvas.getContext('2d', { willReadFrequently: true })
    if (context && video.readyState >= video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth
      canvas.height = video.videoHeight
      context.drawImage(video, 0, 0)
      const value = jsQR(
        context.getImageData(0, 0, canvas.width, canvas.height).data,
        canvas.width,
        canvas.height,
        { inversionAttempts: 'dontInvert' },
      )?.data
      const now = Date.now()
      if (value && (value !== last.value || now - last.at > REPEAT_MS)) {
        last = { value, at: now }
        if (value.startsWith(BOOKING_QR_PREFIX)) {
          error.value = ''
          onCode(value)
        } else error.value = 'not-booking'
      }
    }
    frame = requestAnimationFrame(() => read(video, canvas))
  }

  async function start(video: HTMLVideoElement) {
    stop()
    error.value = ''
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      video.srcObject = stream
      await video.play()
    } catch {
      stop()
      error.value = 'camera'
      return
    }
    scanning.value = true
    read(video, document.createElement('canvas'))
  }

  function stop() {
    scanning.value = false
    cancelAnimationFrame(frame)
    stream?.getTracks().forEach((track) => track.stop())
    stream = null
    last = { value: '', at: 0 }
  }

  onBeforeUnmount(stop)

  return { scanning, error, start, stop }
}
