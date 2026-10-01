(() => {
  'use strict';

  const preferenceKey = 'motora.background-motion';

  function initializeBackground() {
    const layer = document.querySelector('[data-site-background]');
    const video = layer?.querySelector('[data-background-video]');
    const toggle = document.querySelector('[data-background-toggle]');
    if (!layer || !video || !toggle) return;

    const label = toggle.querySelector('[data-motion-label]');
    const icons = toggle.querySelectorAll('[data-motion-icon]');
    let sources = Array.from(video.querySelectorAll('source[data-src]'));
    const motionQuery = typeof window.matchMedia === 'function'
      ? window.matchMedia('(prefers-reduced-motion: reduce)')
      : null;
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    let preference = null;
    let sourcesLoaded = false;
    let playbackFailed = false;
    let playing = false;
    let generation = 0;
    let sourceGeneration = 0;
    let pendingGeneration = null;

    try {
      const savedPreference = window.localStorage.getItem(preferenceKey);
      if (savedPreference === 'play' || savedPreference === 'pause') preference = savedPreference;
    } catch (_) {
      // The control still works when storage is unavailable.
    }

    video.muted = true;
    video.defaultMuted = true;
    video.loop = true;
    video.playsInline = true;

    function updatePresentation(isPlaying) {
      playing = isPlaying;
      layer.classList.toggle('is-playing', isPlaying);
      if (isPlaying) layer.classList.remove('has-video-error');
      const text = isPlaying ? 'Jeda latar' : 'Putar latar';
      toggle.setAttribute('aria-pressed', isPlaying ? 'false' : 'true');
      toggle.setAttribute('aria-label', text);
      if (label) label.textContent = text;
      icons.forEach((icon) => {
        const visible = icon.getAttribute('data-motion-icon') === (isPlaying ? 'pause' : 'play');
        if (visible) icon.removeAttribute('hidden');
        else icon.setAttribute('hidden', '');
      });
    }

    function playbackAllowed() {
      if (preference === 'pause') return false;
      if (preference === 'play') return true;
      return !motionQuery?.matches && !connection?.saveData;
    }

    function pausePlayback() {
      generation += 1;
      pendingGeneration = null;
      video.pause();
      updatePresentation(false);
    }

    function showPoster() {
      if (playbackFailed) return;
      playbackFailed = true;
      layer.classList.add('has-video-error');
      pausePlayback();
    }

    function loadSources() {
      if (sourcesLoaded) return;
      const currentSourceGeneration = ++sourceGeneration;
      sources = sources.map((source) => {
        // A new node keeps a queued error from an earlier load out of this retry.
        const nextSource = source.cloneNode(false);
        const path = nextSource.getAttribute('data-src');
        if (path) nextSource.setAttribute('src', path);
        nextSource.addEventListener('error', () => {
          if (currentSourceGeneration === sourceGeneration) showPoster();
        });
        source.replaceWith(nextSource);
        return nextSource;
      });
      const directPath = video.getAttribute('data-src');
      if (directPath) video.setAttribute('src', directPath);
      sourcesLoaded = true;
      video.load();
    }

    function resumePlayback() {
      if (!playbackAllowed() || document.hidden || playbackFailed || pendingGeneration !== null) return;
      if (playing && !video.paused) return;

      const requestGeneration = ++generation;
      pendingGeneration = requestGeneration;
      try {
        loadSources();
        const playRequest = video.play();
        Promise.resolve(playRequest).then(() => {
          if (requestGeneration !== generation) return;
          pendingGeneration = null;
          if (!playbackAllowed() || document.hidden) {
            pausePlayback();
            return;
          }
          // Older browsers may not return a play promise; the playing event also updates the control.
          if (!video.paused && video.readyState >= 2) updatePresentation(true);
        }).catch(() => {
          if (requestGeneration !== generation) return;
          showPoster();
        });
      } catch (_) {
        if (requestGeneration === generation) showPoster();
      }
    }

    function reconcilePlayback() {
      if (!playbackAllowed() || document.hidden) pausePlayback();
      else resumePlayback();
    }

    toggle.addEventListener('click', () => {
      preference = playing ? 'pause' : 'play';
      try {
        window.localStorage.setItem(preferenceKey, preference);
      } catch (_) {
        // Retain the choice for this page even without persistent storage.
      }
      if (preference === 'play' && playbackFailed) {
        playbackFailed = false;
        sourcesLoaded = false;
        layer.classList.remove('has-video-error');
      }
      reconcilePlayback();
    });

    video.addEventListener('playing', () => {
      if (playbackAllowed() && !document.hidden && !playbackFailed) updatePresentation(true);
      else pausePlayback();
    });
    video.addEventListener('pause', () => {
      if (video.paused) updatePresentation(false);
    });
    video.addEventListener('ended', () => {
      if (video.ended) updatePresentation(false);
    });
    video.addEventListener('error', () => {
      // load() clears video.error; ignore any queued event left over from that load.
      if (video.error) showPoster();
    });
    document.addEventListener('visibilitychange', reconcilePlayback);
    window.addEventListener('pagehide', pausePlayback);
    window.addEventListener('pageshow', reconcilePlayback);

    if (motionQuery?.addEventListener) motionQuery.addEventListener('change', reconcilePlayback);
    else if (motionQuery?.addListener) motionQuery.addListener(reconcilePlayback);
    connection?.addEventListener?.('change', reconcilePlayback);

    updatePresentation(false);
    toggle.hidden = false;
    reconcilePlayback();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeBackground, { once: true });
  } else {
    initializeBackground();
  }
})();
