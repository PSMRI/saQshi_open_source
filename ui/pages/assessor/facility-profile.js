(function (window, document) {
  "use strict"; const SQ = window.SQ || {}; let facId = 0; let hasFreshLocation = false;
  const esc = value => String(value ?? "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
  async function saveCoordinates(latitude, longitude, quiet = false) { await SQ.api.post('/assessor/v1/facility_profile.php', { fac_id: facId, latitude, longitude }); if (!quiet) SQ.notification?.success('Coordinates updated.'); }
  function freshLocation() { return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('Location is not supported by this device/browser.'));
    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
  }); }
  function locationState(text, tone = 'not-started') { const el = document.getElementById('assessorProfileLocationState'); el.textContent = text; el.className = `sq-assessor-status sq-assessor-status--${tone}`; }
  async function captureSchoolLocation() { try { locationState('Requesting location…', 'progress'); const position = await freshLocation(); const latitude = position.coords.latitude; const longitude = position.coords.longitude;
    document.getElementById('assessorProfileLatitude').value = latitude; document.getElementById('assessorProfileLongitude').value = longitude;
    hasFreshLocation = true; document.getElementById('assessorProfileSaveLocation').disabled = false; locationState('Fresh location ready to save', 'progress'); SQ.notification?.success('Fresh current location captured. Save it when ready.');
  } catch (error) { const message = error?.code === 1 ? 'Location permission was not granted.' : (error.message || 'Unable to capture current location.'); locationState('Location unavailable', 'attention'); SQ.notification?.error(message); } }
  async function load() { const r = await SQ.api.get('/assessor/v1/facility_profile.php', { fac_id: facId }); const f = r.data?.facility || {}; const update = r.data?.profile_update || {};
    const facilityLabel = SQ.deployment?.label?.('facility', 'Facility') || 'Facility';
    const profileTitle = `${facilityLabel} Profile`;
    document.getElementById('assessorProfileTitle').textContent = profileTitle;
    const pageTitle = document.getElementById('sq-page-title'); const pageSubtitle = document.getElementById('sq-page-subtitle');
    if (pageTitle) pageTitle.textContent = profileTitle;
    if (pageSubtitle) pageSubtitle.textContent = `Current ${facilityLabel.toLowerCase()} details and GPS location.`;
    document.title = `${profileTitle} | SaQshi`;
    document.getElementById('assessorProfileIntro').textContent = facilityLabel.toLowerCase() === 'school' ? 'Allow location permission to capture and save fresh current GPS coordinates.' : 'Update this assigned facility\'s GPS coordinates.';
    const details = [
      ['Name', f.fac_name], [SQ.deployment?.label?.('facility_code', 'UDISE Code') || 'UDISE Code', f.nin_no],
      ['Type', f.facilities_type], ['Village', f.village], ['State', f.state_name], ['Division', f.division],
      ['District', f.district], ['Block', f.block]
    ].filter(([, value]) => String(value || '').trim());
    document.getElementById('assessorProfileDetails').innerHTML = details.map(([key, value]) => `<div><span>${esc(key)}</span><strong>${esc(value)}</strong></div>`).join('');
    document.getElementById('assessorProfileLatitude').value = f.latitude || 'Not captured'; document.getElementById('assessorProfileLongitude').value = f.longitude || 'Not captured';
    locationState(f.latitude && f.longitude ? 'Location saved' : 'Waiting for location', f.latitude && f.longitude ? 'completed' : 'not-started');
    const hasOtherUpdate = Boolean(update.updated_by_other);
    document.getElementById('assessorProfileOtherUpdate').hidden = !hasOtherUpdate;
    document.getElementById('assessorProfileLocationCard').hidden = hasOtherUpdate;
    if (hasOtherUpdate) {
      const savedCoordinates = f.latitude && f.longitude ? ` Saved coordinates: Latitude ${f.latitude}, Longitude ${f.longitude}.` : ' No saved coordinates are available yet.';
      document.getElementById('assessorProfileOtherUpdateText').textContent = `${profileTitle} was last updated by another assigned assessor${update.assessor_code ? ` (${update.assessor_code})` : ''}${update.updated_on ? ` on ${update.updated_on}` : ''}.${savedCoordinates}`;
    }
    if (facilityLabel.toLowerCase() === 'school' && !hasOtherUpdate) captureSchoolLocation();
  }
  function init() { facId = Number(new URLSearchParams(location.search).get('fac_id') || 0); if (!facId) { SQ.notification?.error('Facility is required.'); return; }
    document.getElementById('assessorProfileBack').onclick = () => SQ.router.navigate('assessor/facilities');
    document.getElementById('assessorProfileRefreshLocation').onclick = captureSchoolLocation;
    document.getElementById('assessorProfileUpdateAnyway').onclick = () => { document.getElementById('assessorProfileOtherUpdate').hidden = true; document.getElementById('assessorProfileLocationCard').hidden = false; captureSchoolLocation(); };
    document.getElementById('assessorProfileSaveLocation').onclick = async () => { if (!hasFreshLocation) return; try { await saveCoordinates(document.getElementById('assessorProfileLatitude').value, document.getElementById('assessorProfileLongitude').value, true); hasFreshLocation = false; document.getElementById('assessorProfileSaveLocation').disabled = true; locationState('Location saved', 'completed'); SQ.notification?.success('Location and master details saved.'); } catch (x) { SQ.notification?.error(x.message || 'Unable to save location.'); } };
    load().catch(x => SQ.notification?.error(x.message || 'Unable to load facility profile.'));
  } SQ.assessorFacilityProfile = { init };
})(window, document);
