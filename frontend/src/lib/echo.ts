import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { apiOrigin } from './api'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo<any>
  }
}

if (typeof window !== 'undefined') {
  window.Pusher = Pusher
}

let echoInstance: Echo<any> | null = null

export function getEcho(): Echo<any> {
  if (echoInstance) {
    return echoInstance
  }

  const host =
    import.meta.env.VITE_REVERB_HOST ||
    (typeof window !== 'undefined' ? window.location.hostname : 'localhost')
  const port = import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 8080
  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'
  const key = import.meta.env.VITE_REVERB_APP_KEY || 'fwmpeywmrzxzbs8w8cke'

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiOrigin}/broadcasting/auth`,
    authorizer: (channel: { name: string }) => {
      return {
        authorize: (socketId: string, callback: (error: Error | null, authData?: any) => void) => {
          fetch(`${apiOrigin}/broadcasting/auth`, {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              ...(typeof document !== 'undefined' && document.cookie.includes('XSRF-TOKEN=')
                ? {
                    'X-XSRF-TOKEN': decodeURIComponent(
                      document.cookie
                        .split('; ')
                        .find((row) => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] || '',
                    ),
                  }
                : {}),
            },
            body: JSON.stringify({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          })
            .then(async (response) => {
              if (!response.ok) {
                callback(new Error(`Broadcasting auth failed with status ${response.status}`))
                return
              }
              const data = await response.json()
              callback(null, data)
            })
            .catch((error: unknown) => {
              callback(error instanceof Error ? error : new Error(String(error)))
            })
        },
      }
    },
  })

  return echoInstance
}

export function subscribeToVenue(
  venueId: number,
  onCourtStatusUpdated: (event: {
    venue_id: number
    court_id: number
    status: string
    metadata?: Record<string, unknown>
    timestamp?: string
  }) => void,
) {
  const echo = getEcho()
  const channel = echo.private(`venue.${venueId}`)

  channel.listen('.CourtStatusUpdated', onCourtStatusUpdated)
  channel.listen('CourtStatusUpdated', onCourtStatusUpdated)

  return () => {
    channel.stopListening('.CourtStatusUpdated')
    channel.stopListening('CourtStatusUpdated')
    echo.leave(`venue.${venueId}`)
  }
}
