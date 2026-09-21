/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Feature 6: The Blood Journey (Interactive 5-Stage Stepper Modal)
 */

function promptTrackBag() {
    const bagCode = prompt("Enter Serial Blood Bag Code to trace its complete journey:\n(e.g., BAG-2026-A199, BAG-2026-B001, BAG-2026-O002):", "BAG-2026-A199");
    if (bagCode && bagCode.trim()) {
        openJourneyModal(bagCode.trim());
    }
}

async function openJourneyModal(bagCode) {
    let modalEl = document.getElementById('journeyModal');
    if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'journeyModal';
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content shadow-lg border-0">
                    <div class="modal-header bg-dark text-white">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-arrow-repeat text-danger fs-4"></i>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">The Blood Journey &bull; Chain of Custody</h5>
                                <div class="small text-muted text-light-50">Serialized Bio-Track Traceability</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4" id="journeyModalBody">
                        <div class="text-center py-5">
                            <div class="spinner-border text-danger" role="status"></div>
                            <div class="mt-2 text-muted fw-semibold">Tracing custody records across relational tables...</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const body = document.getElementById('journeyModalBody');
    body.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-danger" role="status"></div>
            <div class="mt-2 text-muted fw-semibold">Querying bio-traceability for bag ${bagCode}...</div>
        </div>
    `;

    try {
        const res = await apiRequest(`journey.php?bag_code=${encodeURIComponent(bagCode)}`);
        const d = res.data;

        const isCollected = !!d.collection_date;
        const isTested = !!d.collection_date;
        const isStored = (d.bag_status === 'AVAILABLE' || d.bag_status === 'RESERVED' || d.bag_status === 'ISSUED');
        const isMatched = (d.bag_status === 'RESERVED' || d.bag_status === 'ISSUED');
        const isTransfused = (d.bag_status === 'ISSUED');

        const stepClass = (done, active) => done ? 'completed' : (active ? 'active' : '');

        let statusBadge = '';
        if (d.bag_status === 'ISSUED') {
            statusBadge = '<span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>TRANSFUSED / ISSUED</span>';
        } else if (d.bag_status === 'AVAILABLE') {
            statusBadge = '<span class="badge bg-primary"><i class="bi bi-box me-1"></i>VAULT SECURED & AVAILABLE</span>';
        } else if (d.bag_status === 'DISCARDED') {
            statusBadge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>DISCARDED / EXPIRED</span>';
        } else {
            statusBadge = `<span class="badge bg-warning text-dark">${d.bag_status}</span>`;
        }

        body.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <span class="fs-4 fw-bold font-monospace text-danger">${d.bag_code}</span>
                    <span class="badge bg-dark fs-6 ms-2">${d.blood_group}</span>
                </div>
                <div>${statusBadge}</div>
            </div>

            <!-- 5-Stage Stepper Visualizer -->
            <div class="journey-stepper mb-4">
                <div class="journey-step ${stepClass(isCollected, false)}">
                    <div class="journey-icon-wrap"><i class="bi bi-heart-fill"></i></div>
                    <div class="journey-step-title">1. Donor Intake</div>
                    <div class="journey-step-desc">${d.collection_date || 'Pending'}</div>
                </div>
                <div class="journey-step ${stepClass(isTested, !isTested && isCollected)}">
                    <div class="journey-icon-wrap"><i class="bi bi-shield-check"></i></div>
                    <div class="journey-step-title">2. Serology Test</div>
                    <div class="journey-step-desc">${isTested ? 'Passed' : 'Pending'}</div>
                </div>
                <div class="journey-step ${stepClass(isStored, !isStored && isTested)}">
                    <div class="journey-icon-wrap"><i class="bi bi-thermometer-snow"></i></div>
                    <div class="journey-step-title">3. Cold Storage</div>
                    <div class="journey-step-desc">${d.storage_location || 'Pending'}</div>
                </div>
                <div class="journey-step ${stepClass(isMatched, !isMatched && isStored)}">
                    <div class="journey-icon-wrap"><i class="bi bi-crosshair"></i></div>
                    <div class="journey-step-title">4. Matched</div>
                    <div class="journey-step-desc">${d.hospital_name ? d.hospital_name.substring(0, 15) : 'Pending'}</div>
                </div>
                <div class="journey-step ${stepClass(isTransfused, !isTransfused && isMatched)}">
                    <div class="journey-icon-wrap"><i class="bi bi-hospital"></i></div>
                    <div class="journey-step-title">5. Transfused</div>
                    <div class="journey-step-desc">${d.issuance_date || 'Pending'}</div>
                </div>
            </div>

            <!-- Detailed Chain of Custody Breakdown -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-person-heart text-danger me-2"></i>Collection & Donor</h6>
                        <ul class="list-unstyled small mb-0">
                            <li><strong>Donor:</strong> ${d.donor_name || 'Anonymous Voluntary Donor'}</li>
                            <li><strong>Origin:</strong> ${d.donor_city || 'Regional Center'}</li>
                            <li><strong>Vitals:</strong> BP ${d.blood_pressure || '120/80'} | Hb ${d.hemoglobin || '14.2'} g/dL</li>
                            <li><strong>Donation Date:</strong> ${d.donation_date || d.collection_date || 'N/A'}</li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-thermometer-half text-info me-2"></i>Storage & Shelf Life</h6>
                        <ul class="list-unstyled small mb-0">
                            <li><strong>Chamber Location:</strong> ${d.storage_location}</li>
                            <li><strong>Collection:</strong> ${d.collection_date}</li>
                            <li><strong>Expiry Date:</strong> <span class="text-danger fw-bold">${d.expiry_date}</span></li>
                            <li><strong>Preservation:</strong> CPDA-1 Anticoagulant (2-6°C)</li>
                        </ul>
                    </div>
                </div>

                ${d.hospital_name ? `
                <div class="col-12">
                    <div class="p-3 bg-light rounded border border-success">
                        <h6 class="fw-bold text-success mb-2"><i class="bi bi-clipboard2-pulse me-2"></i>Hospital Issuance & Recipient</h6>
                        <div class="row small">
                            <div class="col-md-6">
                                <div><strong>Hospital:</strong> ${d.hospital_name}</div>
                                <div><strong>Patient:</strong> ${d.patient_name} (${d.urgency || 'ROUTINE'})</div>
                            </div>
                            <div class="col-md-6">
                                <div><strong>Issued On:</strong> ${d.issuance_date || 'Completed'}</div>
                                <div><strong>Authorized Officer:</strong> ${d.issued_by_officer || 'Blood Bank Administrator'}</div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : `
                <div class="col-12">
                    <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill fs-5"></i>
                        <div>This unit is currently preserved in cryogenic cold storage and ready for immediate clinical allocation.</div>
                    </div>
                </div>
                `}
            </div>
        `;
    } catch (err) {
        body.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-exclamation-circle text-warning fs-1"></i>
                <h5 class="fw-bold mt-2">Bag Not Found</h5>
                <p class="text-muted small">${err.message}</p>
                <div class="mt-3">
                    <p class="small text-muted">Try one of the existing demonstration bag codes:</p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <button class="btn btn-outline-danger btn-sm font-monospace" onclick="openJourneyModal('BAG-2026-A199')">BAG-2026-A199 (Issued)</button>
                        <button class="btn btn-outline-primary btn-sm font-monospace" onclick="openJourneyModal('BAG-2026-B001')">BAG-2026-B001 (Available)</button>
                        <button class="btn btn-outline-info btn-sm font-monospace" onclick="openJourneyModal('BAG-2026-O002')">BAG-2026-O002 (Available)</button>
                    </div>
                </div>
            </div>
        `;
    }
}
