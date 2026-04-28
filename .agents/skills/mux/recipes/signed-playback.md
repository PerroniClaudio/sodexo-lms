# Signed Playback URLs (Mux)

## Purpose

Restrict who can view or download videos

using time-limited, signed playback URLs.

## When to Use Signed Playback

- Private videos
- Paid content
- Enterprise or internal media
- MP4 download protection

## How It Works

- Playback ID is created as "signed"
- Backend generates a JWT token
- Token is appended to the playback URL
- URL expires after a defined time

## Benefits

- Prevents link sharing
- Full access control
- Works for both streaming and MP4

## Limitations

- Signed URLs must be generated server-side
- Clock skew can invalidate tokens
- Expiry is mandatory

## Best Practices

- Keep expiration short for downloads
- Regenerate URLs on demand
- Log access if required

## Notes

Signed playback is strongly recommended

when enabling MP4 downloads.

``
