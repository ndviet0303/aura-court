import jsQR from 'jsqr'
import { onBeforeUnmount, ref } from 'vue'

// The camera sees the same code for many frames; hand it over once per hold.
const REPEAT_MS = 3000

/**
 * Reads QR codes from the back camera and hands each value to `accept` once per hold. `accept` returns false for a
 * QR it does not take, which sets error `unrecognized`; a camera that is denied or missing sets error `camera`.
 */
export function useQrScanner(accept: (value: string) => boolean) {
  const scanning = ref(false)
  const error = ref<'camera' | 'unrecognized' | ''>('')
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
        error.value = accept(value) ? '' : 'unrecognized'
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
