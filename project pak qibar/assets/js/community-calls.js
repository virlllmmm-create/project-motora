document.addEventListener('DOMContentLoaded', () => {
  const group = window.MotoraGroup;
  if (!group) return;
  const { root, request, report, uid } = group;
  const banner = root.querySelector('[data-call-banner]');
  const room = root.querySelector('[data-call-room]');
  const videos = root.querySelector('[data-call-videos]');
  const status = root.querySelector('[data-call-status]');
  const micButton = root.querySelector('[data-call-mic]');
  const cameraButton = root.querySelector('[data-call-camera]');
  const peers = new Map();
  let call = null;
  let stream = null;
  let session = '';
  let iceServers = [];
  let cursor = 0;
  let pollTimer = null;
  let busy = false;
  let generation = 0;
  const callRequest = (action, fields = {}) => request(root.dataset.callUrl, { call_action: action, call_id: call?.id || '', session, ...fields });
  const tile = (id, name, media, local = false) => {
    const container = document.createElement('div'); container.className = 'group-video-tile'; container.dataset.peerId = id;
    const video = document.createElement('video'); video.autoplay = true; video.playsInline = true; video.muted = local; video.srcObject = media;
    if (!media.getVideoTracks().length) video.style.display = 'none';
    const label = document.createElement('span'); label.textContent = name;
    container.append(video, label); videos.append(container); video.play().catch(() => {});
    return container;
  };
  const removePeer = (id) => {
    const peer = peers.get(id); if (!peer) return;
    peer.connection.onicecandidate = null; peer.connection.ontrack = null; peer.connection.close(); peer.tile?.remove(); peers.delete(id);
  };
  const updateStatus = () => {
    if (!call) return;
    const connected = [...peers.values()].filter((p) => p.connection.connectionState === 'connected').length;
    status.textContent = `${call.kind === 'video' ? 'Panggilan video' : 'Panggilan suara'} · ${call.participants.length} peserta${peers.size && connected < peers.size ? ' · Menghubungkan…' : ''}`;
    const failed = [...peers.values()].some((p) => p.connection.connectionState === 'failed');
    if (failed) status.textContent += ' · Koneksi peserta gagal. Coba keluar lalu gabung kembali.';
  };
  const signal = (peer, data) => callRequest('signal', { recipient: peer.id, recipient_session: peer.session, payload: JSON.stringify(data) });
  const createPeer = (participant) => {
    const existing = peers.get(participant.id);
    if (existing?.session === participant.session) return existing;
    if (existing) removePeer(participant.id);
    const connection = new RTCPeerConnection({ iceServers });
    const peer = { id: participant.id, session: participant.session, name: participant.name, connection, candidates: [], offered: false, tile: null };
    peers.set(peer.id, peer);
    stream.getTracks().forEach((track) => connection.addTrack(track, stream));
    connection.onicecandidate = (event) => { if (event.candidate && call) signal(peer, { type: 'candidate', candidate: event.candidate.toJSON() }).catch(() => {}); };
    connection.ontrack = (event) => {
      const media = event.streams[0] || new MediaStream([event.track]);
      if (!peer.tile) peer.tile = tile(peer.id, peer.name, media);
    };
    connection.onconnectionstatechange = updateStatus;
    return peer;
  };
  const syncPeers = async (participants) => {
    const activeIds = participants.filter((p) => p.id !== uid).map((p) => p.id);
    for (const id of peers.keys()) if (!activeIds.includes(id)) removePeer(id);
    for (const participant of participants) {
      if (participant.id === uid) continue;
      const peer = createPeer(participant);
      // Exactly one side initiates, avoiding simultaneous offers.
      if (uid > participant.id && !peer.offered) {
        peer.offered = true;
        await peer.connection.setLocalDescription(await peer.connection.createOffer());
        await signal(peer, { type: 'offer', sdp: peer.connection.localDescription.sdp });
      }
    }
    updateStatus();
  };
  const receive = async (message) => {
    const participant = call.participants.find((p) => p.id === message.sender && p.session === message.session);
    if (!participant) return;
    const peer = createPeer(participant); const { connection } = peer; const data = message.data;
    if (data.type === 'candidate') {
      if (connection.remoteDescription) await connection.addIceCandidate(data.candidate);
      else peer.candidates.push(data.candidate);
    } else if (data.type === 'offer' || data.type === 'answer') {
      await connection.setRemoteDescription({ type: data.type, sdp: data.sdp });
      for (const candidate of peer.candidates.splice(0)) await connection.addIceCandidate(candidate);
      if (data.type === 'offer') {
        await connection.setLocalDescription(await connection.createAnswer());
        await signal(peer, { type: 'answer', sdp: connection.localDescription.sdp });
      }
    }
  };
  const reset = () => {
    generation++; clearTimeout(pollTimer); pollTimer = null;
    for (const id of [...peers.keys()]) removePeer(id);
    if (stream) stream.getTracks().forEach((track) => track.stop());
    stream = null; call = null; session = ''; cursor = 0;
    videos.replaceChildren(); room.hidden = true;
    micButton.textContent = 'Bisukan mic'; cameraButton.textContent = 'Matikan kamera';
    root.querySelectorAll('[data-call-start],[data-call-join]').forEach((button) => { button.disabled = false; });
  };
  const leave = async (notify = true) => {
    if (!call) return reset();
    const data = { call_action: 'leave', call_id: call.id, session, csrf: root.dataset.csrf };
    reset();
    if (notify) { try { await request(root.dataset.callUrl, data); } catch (_) {} }
    group.refresh();
  };
  const poll = async (token) => {
    if (!call || generation !== token) return;
    try {
      const state = await callRequest('poll', { after: cursor });
      if (generation !== token || !call) return;
      if (!state.call) { await leave(false); return; }
      call = state.call;
      await syncPeers(call.participants);
      for (const message of state.signals) {
        try { await receive(message); } catch (error) { status.textContent = 'Koneksi peserta gagal: ' + error.message; }
        cursor = Math.max(cursor, message.id);
      }
    } catch (error) {
      if (generation !== token) return;
      report(error.message);
      if (/berakhir|anggota aktif|tidak valid|ditutup/.test(error.message)) { await leave(false); return; }
    }
    if (call && generation === token) pollTimer = setTimeout(() => poll(token), 1000);
  };
  const begin = async (kind, join = false) => {
    if (busy || call) return;
    busy = true; report();
    root.querySelectorAll('[data-call-start],[data-call-join]').forEach((button) => { button.disabled = true; });
    try {
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) throw new Error('Panggilan memerlukan browser yang mendukung kamera/mikrofon di localhost atau HTTPS.');
      stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true }, video: kind === 'video' ? { width: { ideal: 640 }, height: { ideal: 360 } } : false });
      const state = await callRequest(join ? 'join' : 'start', { kind, call_id: join ? group.state?.call?.id : '' });
      call = state.call; session = state.session; iceServers = state.ice_servers; cursor = 0;
      banner.hidden = true; room.hidden = false; cameraButton.hidden = !stream.getVideoTracks().length;
      tile(uid, 'Anda', stream, true); updateStatus();
      const token = ++generation; await poll(token); group.refresh();
    } catch (error) {
      reset();
      const messages = { NotAllowedError: 'Izinkan akses mikrofon dan kamera untuk mengikuti panggilan.', NotFoundError: 'Mikrofon atau kamera tidak ditemukan.', NotReadableError: 'Mikrofon atau kamera sedang digunakan aplikasi lain.' };
      report(messages[error.name] || error.message);
    } finally { busy = false; }
  };
  root.querySelectorAll('[data-call-start]').forEach((button) => button.addEventListener('click', () => begin(button.dataset.callStart)));
  root.querySelector('[data-call-join]').addEventListener('click', () => { if (group.state?.call) begin(group.state.call.kind, true); });
  root.querySelector('[data-call-leave]').addEventListener('click', () => leave());
  root.querySelector('[data-call-end]')?.addEventListener('click', async () => {
    if (!call || !window.confirm('Akhiri panggilan untuk semua peserta?')) return;
    try { await callRequest('end'); await leave(false); } catch (error) { report(error.message); }
  });
  micButton.addEventListener('click', () => {
    if (!stream) return; const tracks = stream.getAudioTracks(); const enabled = !tracks[0]?.enabled;
    tracks.forEach((track) => { track.enabled = enabled; }); micButton.textContent = enabled ? 'Bisukan mic' : 'Aktifkan mic'; micButton.setAttribute('aria-pressed', String(!enabled));
  });
  cameraButton.addEventListener('click', () => {
    if (!stream) return; const tracks = stream.getVideoTracks(); const enabled = !tracks[0]?.enabled;
    tracks.forEach((track) => { track.enabled = enabled; }); cameraButton.textContent = enabled ? 'Matikan kamera' : 'Aktifkan kamera'; cameraButton.setAttribute('aria-pressed', String(!enabled));
  });
  window.addEventListener('community:state', (event) => {
    const active = event.detail.call;
    if (call && (event.detail.closed || !active || active.id !== call.id)) { leave(false); return; }
    banner.hidden = !!call || !active;
    if (active && !call) root.querySelector('[data-call-banner-text]').textContent = `${active.started_name} memulai panggilan ${active.kind === 'video' ? 'video' : 'suara'} · ${active.participants.length} peserta`;
  });
  window.addEventListener('community:access-lost', () => leave(false));
  window.addEventListener('pagehide', () => {
    if (call) {
      const body = new URLSearchParams({ call_action: 'leave', call_id: call.id, session, csrf: root.dataset.csrf });
      navigator.sendBeacon(root.dataset.callUrl, body);
    }
    reset();
  });
});
