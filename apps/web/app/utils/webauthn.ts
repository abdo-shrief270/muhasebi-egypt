/**
 * Passkeys (fingerprint / face) in the browser. The API sends WebAuthn options with base64url
 * strings; these helpers turn them into what navigator.credentials wants and back.
 */

export interface PasskeyCreateOptions {
  challenge: string
  rp: { id: string, name: string }
  user: { id: string, name: string, displayName: string }
  pubKeyCredParams: { type: 'public-key', alg: number }[]
  authenticatorSelection: AuthenticatorSelectionCriteria
  excludeCredentials: { type: 'public-key', id: string }[]
  attestation: AttestationConveyancePreference
  timeout: number
}

export interface PasskeyGetOptions {
  challenge: string
  rpId: string
  allowCredentials: { type: 'public-key', id: string }[]
  userVerification: UserVerificationRequirement
  timeout: number
}

export interface PasskeyRegistration {
  id: string
  client_data_json: string
  authenticator_data: string
  public_key: string
  alg: number
}

export interface PasskeyAssertion {
  id: string
  client_data_json: string
  authenticator_data: string
  signature: string
}

export function toBase64Url(buffer: ArrayBuffer): string {
  let text = ''
  for (const byte of new Uint8Array(buffer)) {
    text += String.fromCharCode(byte)
  }
  return btoa(text).replaceAll('+', '-').replaceAll('/', '_').replace(/=+$/, '')
}

export function fromBase64Url(text: string): ArrayBuffer {
  const base64 = text.replaceAll('-', '+').replaceAll('_', '/') + '==='.slice((text.length + 3) % 4)
  const bytes = Uint8Array.from(atob(base64), c => c.charCodeAt(0))
  return bytes.buffer
}

/** This browser can make passkeys at all (and the page is on HTTPS or localhost). */
export function passkeysSupported(): boolean {
  return import.meta.client && window.isSecureContext && typeof window.PublicKeyCredential === 'function'
}

/** The device has a built-in fingerprint / face / PIN unlock for passkeys. */
export async function platformPasskeyAvailable(): Promise<boolean> {
  if (!passkeysSupported()) {
    return false
  }
  try {
    return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
  }
  catch {
    return false
  }
}

/** Asks for the fingerprint and makes a new passkey. Null when the person cancels. */
export async function createPasskey(options: PasskeyCreateOptions): Promise<PasskeyRegistration | null> {
  const credential = await navigator.credentials.create({
    publicKey: {
      ...options,
      challenge: fromBase64Url(options.challenge),
      user: { ...options.user, id: fromBase64Url(options.user.id) },
      excludeCredentials: options.excludeCredentials.map(c => ({ type: c.type, id: fromBase64Url(c.id) })),
    },
  }).catch(cancelled) as PublicKeyCredential | null
  if (!credential) {
    return null
  }
  const response = credential.response as AuthenticatorAttestationResponse
  const publicKey = response.getPublicKey?.()
  if (!publicKey) {
    throw new Error('المتصفح ده قديم شوية ومش بيدعم البصمة. حدّثه وجرّب تاني.')
  }
  return {
    id: credential.id,
    client_data_json: toBase64Url(response.clientDataJSON),
    authenticator_data: toBase64Url(response.getAuthenticatorData()),
    public_key: toBase64Url(publicKey),
    alg: response.getPublicKeyAlgorithm(),
  }
}

/** Asks for the fingerprint and signs the server's challenge. Null when the person cancels. */
export async function getPasskey(options: PasskeyGetOptions): Promise<PasskeyAssertion | null> {
  const credential = await navigator.credentials.get({
    publicKey: {
      ...options,
      challenge: fromBase64Url(options.challenge),
      allowCredentials: options.allowCredentials.map(c => ({ type: c.type, id: fromBase64Url(c.id) })),
    },
  }).catch(cancelled) as PublicKeyCredential | null
  if (!credential) {
    return null
  }
  const response = credential.response as AuthenticatorAssertionResponse
  return {
    id: credential.id,
    client_data_json: toBase64Url(response.clientDataJSON),
    authenticator_data: toBase64Url(response.authenticatorData),
    signature: toBase64Url(response.signature),
  }
}

/** The person closed the fingerprint prompt (or it timed out): not an error to show. */
function cancelled(error: unknown): null {
  if (error instanceof DOMException && (error.name === 'NotAllowedError' || error.name === 'AbortError')) {
    return null
  }
  throw error
}
