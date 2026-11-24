import { Page } from '@playwright/test';

/**
 * Injects browser mocks for SpeechRecognition and MediaRecorder/getUserMedia
 * so E2E tests run deterministically in CI without physical microphones or AI keys.
 */
export async function injectBrowserMocks(page: Page, options: { mockTranscript?: string } = {}) {
  const initialTranscript = options.mockTranscript || 'buka buku saku';

  await page.addInitScript(({ defaultTranscript }) => {
    (window as any).__mockSpeechTranscript = defaultTranscript;

    // 1. Mock SpeechRecognition / webkitSpeechRecognition
    class MockSpeechRecognition extends EventTarget {
      continuous: boolean = false;
      interimResults: boolean = false;
      lang: string = 'id-ID';
      onstart: ((ev: Event) => void) | null = null;
      onresult: ((ev: any) => void) | null = null;
      onerror: ((ev: any) => void) | null = null;
      onend: ((ev: Event) => void) | null = null;
      private _active: boolean = false;
      private _timeout: any = null;

      start() {
        if (this._active) return;
        this._active = true;
        if (this.onstart) this.onstart(new Event('start'));

        const transcript = (window as any).__mockSpeechTranscript || 'buka buku saku';
        this._timeout = setTimeout(() => {
          if (!this._active) return;
          const fakeEvent = {
            resultIndex: 0,
            results: [
              Object.assign(
                [
                  {
                    transcript,
                    confidence: 0.98,
                  },
                ],
                { isFinal: true }
              ),
            ],
          };
          if (this.onresult) this.onresult(fakeEvent);
          this.dispatchEvent(new CustomEvent('result', { detail: fakeEvent }));
          this.stop();
        }, 150);
      }

      stop() {
        if (!this._active) return;
        this._active = false;
        if (this._timeout) clearTimeout(this._timeout);
        if (this.onend) this.onend(new Event('end'));
        this.dispatchEvent(new Event('end'));
      }

      abort() {
        this.stop();
      }
    }

    (window as any).SpeechRecognition = MockSpeechRecognition;
    (window as any).webkitSpeechRecognition = MockSpeechRecognition;

    // 2. Mock navigator.mediaDevices.getUserMedia
    if (!navigator.mediaDevices) {
      (navigator as any).mediaDevices = {};
    }

    navigator.mediaDevices.getUserMedia = async () => {
      try {
        const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const dst = ctx.createMediaStreamDestination();
        osc.connect(dst);
        osc.start();
        return dst.stream;
      } catch (e) {
        return new MediaStream();
      }
    };

    // 3. Mock MediaRecorder
    class MockMediaRecorder extends EventTarget {
      stream: MediaStream;
      mimeType: string;
      state: 'inactive' | 'recording' | 'paused';
      ondataavailable: ((ev: BlobEvent) => void) | null = null;
      onstart: ((ev: Event) => void) | null = null;
      onstop: ((ev: Event) => void) | null = null;
      onerror: ((ev: any) => void) | null = null;

      constructor(stream: MediaStream, options: any = {}) {
        super();
        this.stream = stream;
        this.mimeType = options.mimeType || 'audio/webm';
        this.state = 'inactive';
      }

      start() {
        this.state = 'recording';
        if (this.onstart) this.onstart(new Event('start'));
      }

      stop() {
        this.state = 'inactive';
        const blob = new Blob([new Uint8Array([0x1a, 0x45, 0xdf, 0xa3])], { type: this.mimeType });
        const event = new BlobEvent('dataavailable', { data: blob });
        if (this.ondataavailable) this.ondataavailable(event);
        if (this.onstop) this.onstop(new Event('stop'));
        this.dispatchEvent(new Event('stop'));
      }
    }

    (window as any).MediaRecorder = MockMediaRecorder;
  }, { defaultTranscript: initialTranscript });
}

export async function setMockSpeechTranscript(page: Page, transcript: string) {
  await page.evaluate((text) => {
    (window as any).__mockSpeechTranscript = text;
  }, transcript);
}
