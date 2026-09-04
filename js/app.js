// ===== CLUB-COLLAB DASHBOARD – MAIN APP =====
let equipmentData = [];
let clubsData = [];
let studentsData = [];
let eventsData = [];
let volunteersData = [];
let bookingsData = [];
let badgesData = [];
let leaderboardData = [];
let maintenanceData = [];
let membershipsData = [];
let currentUser = null;

// ===== ROLE-BASED ACCESS CONTROL =====
const publicSections = ['clubs', 'events', 'need', 'volunteers', 'badges'];
const adminOnlySections = ['dashboard', 'students', 'equipment', 'maintenance', 'joine', 'analytics'];

function applyRoleBasedAccess() {
    const isAdmin = currentUser && currentUser.role === 'admin';

    // ----- 1. SIDEBAR LINKS -----
    document.querySelectorAll('.nav-link').forEach(link => {
        const target = link.dataset.target;
        if (isAdmin) {
            link.style.display = ''; // show all
        } else {
            // Non-admin: show only public sections
            if (publicSections.includes(target)) {
                link.style.display = '';
            } else {
                link.style.display = 'none';
            }
        }
    });

    // ----- 2. SECTIONS (display/hide) -----
    document.querySelectorAll('.section').forEach(section => {
        const id = section.id;
        const target = id.replace('section-', '');
        if (isAdmin) {
            section.style.display = ''; // show all
        } else {
            if (publicSections.includes(target)) {
                section.style.display = '';
            } else {
                section.style.display = 'none';
            }
        }
    });

    // ----- 3. REDIRECT if current section is hidden -----
    const activeSection = document.querySelector('.section.active');
    if (activeSection) {
        const activeId = activeSection.id.replace('section-', '');
        if (!isAdmin && !publicSections.includes(activeId)) {
            // Switch to first public section (clubs)
            switchSection('clubs');
            // Also update active link
            document.querySelectorAll('.nav-link').forEach(l => {
                l.classList.toggle('active', l.dataset.target === 'clubs');
            });
        }
    }

    // ----- 4. HIDE ALL ADD BUTTONS FOR NON-ADMIN -----
    const addButtons = [
        'addClubBtn', 'addEventBtn', 'addBookingBtn',
        'addVolunteerBtn', 'addVolunteerLogBtn',
        'addEquipmentBtn', 'addStudentBtn', 'addMaintenanceBtn', 'addMembershipBtn'
    ];
    addButtons.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.style.display = isAdmin ? '' : 'none';
        }
    });

    // ----- 5. HIDE ALL ACTION BUTTONS (Edit/Delete) FOR NON-ADMIN -----
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.style.display = isAdmin ? '' : 'none';
    });
}

// --- DOM Ready ---
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    applyRoleBasedAccess();   
    bindNavLinks();
    bindHeaderButtons();
    bindSearch();
    bindTableActions();
    bindCRUDButtons();
    bindAnalyticsTabs();
    fetchAllData();
});

// --- safeFetchJSON helper ---
async function safeFetchJSON(url) {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        throw new Error(`Invalid JSON from ${url}: ${text.substring(0, 100)}`);
    }
}

// --- Authentication ---
function checkAuth() {
    const user = localStorage.getItem('user');
    if (!user) {
        window.location.href = 'login.html';
        return;
    }
    currentUser = JSON.parse(user);
    document.getElementById('userName').textContent = currentUser.Name;
    document.getElementById('logoutBtn').addEventListener('click', () => {
        localStorage.removeItem('user');
        window.location.href = 'login.html';
    });
}

function bindCRUDButtons() {
    const modalClose = document.getElementById('modalClose');
    if (modalClose) modalClose.addEventListener('click', closeModal);
    const modalCancel = document.getElementById('modalCancel');
    if (modalCancel) modalCancel.addEventListener('click', closeModal);
}

function bindAnalyticsTabs() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.analytics-tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        });
    });
}

function bindNavLinks() {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const target = link.dataset.target;
            switchSection(target);
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            if (window.innerWidth <= 1100) document.getElementById('sidebar')?.classList.remove('show');
        });
    });
}

function bindHeaderButtons() {
    const navToggle = document.getElementById('navToggle');
    if (navToggle) navToggle.addEventListener('click', () => document.getElementById('sidebar')?.classList.toggle('show'));

    document.querySelectorAll('#addEquipmentBtn').forEach(btn => btn.addEventListener('click', addEquipment));
    const bookingBtn = document.getElementById('addBookingBtn');
    if (bookingBtn) bookingBtn.addEventListener('click', addBooking);
    const clubBtn = document.getElementById('addClubBtn');
    if (clubBtn) clubBtn.addEventListener('click', addClub);
    const studentBtn = document.getElementById('addStudentBtn');
    if (studentBtn) studentBtn.addEventListener('click', addStudent);
    const eventBtn = document.getElementById('addEventBtn');
    if (eventBtn) eventBtn.addEventListener('click', addEvent);
    const maintenanceBtn = document.getElementById('addMaintenanceBtn');
    if (maintenanceBtn) maintenanceBtn.addEventListener('click', addMaintenance);
    const membershipBtn = document.getElementById('addMembershipBtn');
    if (membershipBtn) membershipBtn.addEventListener('click', addMembership);
    const volunteerBtn = document.getElementById('addVolunteerBtn');
    if (volunteerBtn) volunteerBtn.addEventListener('click', addVolunteer);
}

function bindSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#equipmentBody tr, #clubsBody tr, #studentsBody tr, #eventsBody tr, #volunteersBody tr, #needBody tr, #maintenanceBody tr, #joineBody tr').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }
}

function bindTableActions() {
    document.addEventListener('click', handleTableButtonClick);
}

function handleTableButtonClick(e) {
    const button = e.target.closest('.action-btn');
    if (!button) return;

    const row = button.closest('tr');
    const table = button.closest('table');
    if (!table || !row) return;

    const action = button.title.toLowerCase(); // "edit" or "delete"
    const tableId = table.id;

    // =========================================
    // 1. MAINTENANCE (composite: Equip_ID + Log_ID)
    // =========================================
    if (tableId === 'maintenanceTable') {
        const equipId = button.dataset.equip || row.dataset.equip;
        const logId = button.dataset.log || row.dataset.log;
        if (!equipId || !logId) {
            alert('Missing maintenance log IDs');
            return;
        }
        const ids = { equip_id: equipId, log_id: logId };
        if (action === 'edit') {
            openEditModal('maintenance', ids);
        } else if (action === 'delete') {
            handleDelete('maintenance', ids, row);
        }
        return;
    }

    // =========================================
    // 2. BOOKINGS (composite: Equip_ID + Event_ID) – Delete only
    // =========================================
    if (tableId === 'bookingsTable' || tableId === 'needTable') {
        let equipId = button.dataset.equip || row.dataset.equip;
        let eventId = button.dataset.event || row.dataset.event;
        if (!equipId || !eventId) {
            // Fallback: try to get from data using names
            const tds = row.querySelectorAll('td');
            if (tds.length >= 2) {
                const equipName = tds[0].textContent.trim();
                const eventName = tds[1].textContent.trim();
                const found = bookingsData.find(b =>
                    (b.EquipmentName || b.Equip_ID).toString() === equipName &&
                    (b.EventTitle || b.Event_ID).toString() === eventName
                );
                if (found) {
                    equipId = found.Equip_ID;
                    eventId = found.Event_ID;
                }
            }
        }
        if (!equipId || !eventId) {
            alert('Equipment ID and Event ID required');
            return;
        }
        const ids = { equip_id: equipId, event_id: eventId };
        if (action === 'delete') {
            handleDelete('bookings', ids, row);
        }
        return;
    }

    // =========================================
    // 3. VOLUNTEERS (composite: Student_ID + Event_ID)
    // =========================================
    if (tableId === 'volunteersTable') {
        let studentId = button.dataset.student || row.dataset.student;
        let eventId = button.dataset.event || row.dataset.event;
        if (!studentId || !eventId) {
            const tds = row.querySelectorAll('td');
            if (tds.length >= 2) {
                const studentName = tds[0].textContent.trim();
                const eventName = tds[1].textContent.trim();
                const found = volunteersData.find(v =>
                    (v.StudentName || v.Student_ID).toString() === studentName &&
                    (v.EventTitle || v.Event_ID).toString() === eventName
                );
                if (found) {
                    studentId = found.Student_ID;
                    eventId = found.Event_ID;
                }
            }
        }
        if (!studentId || !eventId) {
            alert('Student ID and Event ID required');
            return;
        }
        const ids = { student_id: studentId, event_id: eventId };
        if (action === 'edit') {
            openEditModal('volunteers', ids);
        } else if (action === 'delete') {
            handleDelete('volunteers', ids, row);
        }
        return;
    }

    // =========================================
    // 4. MEMBERSHIPS (composite: Student_ID + Club_ID)
    // =========================================
    if (tableId === 'joineTable') {
        let studentId = button.dataset.student || row.dataset.student;
        let clubId = button.dataset.club || row.dataset.club;
        if (!studentId || !clubId) {
            const tds = row.querySelectorAll('td');
            if (tds.length >= 2) {
                const studentName = tds[0].textContent.trim();
                const clubName = tds[1].textContent.trim();
                const found = membershipsData.find(m =>
                    (m.StudentName || m.Student_ID).toString() === studentName &&
                    (m.ClubName || m.Club_ID).toString() === clubName
                );
                if (found) {
                    studentId = found.Student_ID;
                    clubId = found.Club_ID;
                }
            }
        }
        if (!studentId || !clubId) {
            alert('Student ID and Club ID required');
            return;
        }
        const ids = { student_id: studentId, club_id: clubId };
        if (action === 'edit') {
            openEditModal('memberships', ids);
        } else if (action === 'delete') {
            handleDelete('memberships', ids, row);
        }
        return;
    }

    // =========================================
    // 5. SINGLE-KEY TABLES: Equipment, Clubs, Students, Events
    // =========================================
    let id = button.dataset.id || row.dataset.id;
    if (!id) {
        // If no data-id, try the first cell (assuming it contains the ID)
        const firstTd = row.querySelector('td');
        if (firstTd) {
            // Remove '#' and trim
            id = firstTd.textContent.trim().replace(/^#/, '');
        }
    }
    if (!id) {
        alert('Could not find record ID');
        return;
    }

    // Map table IDs to section names
    const sectionMap = {
        equipmentTable: 'equipment',
        clubsTable: 'clubs',
        studentsTable: 'students',
        eventsTable: 'events'
    };
    const section = sectionMap[tableId];

    if (!section) {
        alert('Unknown table: ' + tableId);
        return;
    }

    // Debug: log the action and ID
    console.log(`Action: ${action}, Section: ${section}, ID: ${id}`);

    if (action === 'edit') {
        openEditModal(section, id);
    } else if (action === 'delete') {
        handleDelete(section, id, row);
    }
}

function switchSection(target) {
    const isAdmin = currentUser && currentUser.role === 'admin';

    // Block access to admin-only sections for non-admin
    if (!isAdmin && !publicSections.includes(target)) {
        alert('You do not have permission to view this section.');
        return;
    }

    // Toggle sections
    document.querySelectorAll('.section').forEach(section => {
        section.classList.toggle('active', section.id === `section-${target}`);
    });

    // Update page title
    const titles = {
        dashboard: 'Dashboard',
        clubs: 'Clubs Overview',
        students: 'Members Roster',
        equipment: 'Equipment Manager',
        maintenance: 'Maintenance Logs',
        events: 'Events Overview',
        need: 'Equipment Bookings',
        volunteers: 'Volunteers Overview',
        joine: 'Club Memberships',
        leaderboard: 'Volunteer Leaderboard',
        badges: 'Badges & Leaderboard',
        analytics: 'Analytics & Reports'
    };

    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        pageTitle.innerText = titles[target] || 'Club-Collab';
    }

    // Load specific data for certain sections
    if (target === 'leaderboard') {
        setTimeout(fetchLeaderboard, 100);
    }
    if (target === 'badges') {   
        setTimeout(fetchLeaderboard, 100);
    }
}

function setActiveLink(link) {
    document.querySelectorAll('.nav-link').forEach(item => item.classList.remove('active'));
    link.classList.add('active');
}

function closeSidebarOnMobile() {
    if (window.innerWidth <= 1100) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.remove('show');
    }
}

// === FETCH ALL DATA ===
async function fetchAllData() {
    await Promise.all([
        fetchEquipment(),
        fetchClubs(),
        fetchStudents(),
        fetchEvents(),
        fetchVolunteers(),
        fetchBookings(),
        fetchBadges(),
        fetchMaintenance(),
        fetchMemberships()
    ]);
    await fetchDashboardStats();  // <-- Add this
    if (typeof initializeFilters === 'function') setTimeout(initializeFilters, 500);
}
function initializeFilters() { 
    console.log('Filters ready');
}

// === EQUIPMENT ===
async function fetchEquipment() {
    try {
        const data = await safeFetchJSON('backend/equipment.php');
        equipmentData = Array.isArray(data) ? data : [];
        updateKPIs(equipmentData);
        renderEquipmentRows(equipmentData);
    } catch (error) {
        console.error('Error fetching equipment:', error);
        const tbody = document.getElementById('equipmentBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="7">Error: ${error.message}</td></tr>`;
    }
}
function renderEquipmentRows(data) {
    const tbody = document.getElementById('equipmentBody');
    if (!tbody) return;
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="7">No equipment</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(item => `
        <tr data-id="${item.Equip_ID}">
            <td>${item.Equip_ID}</td>
            <td>${item.Name}</td>
            <td>${item.Type}</td>
            <td>${item.OwnerClub || 'Unassigned'}</td>
            <td><span class="status ${item.Status.toLowerCase().replace(/\s+/g, '-')}">${item.Status}</span></td>
            <td>${item.Purchase_Date || 'N/A'}</td>
            <td>
                <button class="action-btn" data-id="${item.Equip_ID}" title="Edit"><i class="fa-solid fa-pen"></i></button>
                <button class="action-btn" data-id="${item.Equip_ID}" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}
function updateKPIs(data) {
    const total = data.length;
    const available = data.filter(i => i.Status === 'Available').length;
    const damaged = data.filter(i => i.Status === 'Damaged' || i.Status === 'Maintenance').length;
    document.getElementById('totalEquip').innerText = total;
    document.getElementById('availEquip').innerText = available;
    document.getElementById('damagedEquip').innerText = damaged;
}

// === CLUBS ===
async function fetchClubs() {
    try {
        const data = await safeFetchJSON('backend/clubs.php');
        clubsData = Array.isArray(data) ? data : [];
        updateClubSummary();
        renderClubRows(clubsData);
    } catch (error) {
        console.error('Error fetching clubs:', error);
        const tbody = document.getElementById('clubsBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="7">Error: ${error.message}</td></tr>`;
    }
}
function renderClubRows(data) {
    const tbody = document.getElementById('clubsBody');
    if (!tbody) return;
    if (!data || !data.length) { tbody.innerHTML = '<tr><td colspan="7">No clubs</td></tr>'; return; }
    tbody.innerHTML = data.map(item => `
        <tr>
            <td>${item.Club_ID}</td>
            <td>${item.Name}</td>
            <td>${item.Department}</td>
            <td>${item.Office_Room || 'N/A'}</td>
            <td>${item.ContactEmails || 'N/A'}</td>
            <td>${item.MemberCount || 0}</td>
            <td><button class="action-btn" title="Edit"><i class="fa-solid fa-pen"></i></button><button class="action-btn" title="Delete"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `).join('');
}
function updateClubSummary() {
    document.getElementById('totalClubs').innerText = clubsData.length;
    document.getElementById('totalMembers').innerText = clubsData.reduce((s, c) => s + (parseInt(c.MemberCount) || 0), 0);
    document.getElementById('clubContacts').innerText = clubsData.filter(c => c.ContactEmails).length;
}

// === STUDENTS / MEMBERS ===
async function fetchStudents() {
    try {
        const data = await safeFetchJSON('backend/students.php');
        studentsData = Array.isArray(data) ? data : [];
        renderStudentRows(studentsData);
    } catch (error) {
        console.error('Error fetching students:', error);
        const tbody = document.getElementById('studentsBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="9">Error: ${error.message}</td></tr>`;
    }
}
function renderStudentRows(data) {
    const tbody = document.getElementById('studentsBody');
    if (!tbody) return;
    if (!data || !data.length) { tbody.innerHTML = '<tr><td colspan="9">No members</td></tr>'; return; }
    tbody.innerHTML = data.map(item => `
        <tr>
            <td>${item.Student_ID}</td>
            <td>${item.Name}</td>
            <td>${item.Email}</td>
            <td>${item.Street}</td>
            <td>${item.Sub_district}</td>
            <td>${item.District}</td>
            <td>${(item.Phone_Numbers || []).join(', ')}</td>
            <td>${(item.Memberships || []).map(m => `Club ${m.Club_ID} (${m.Designation})`).join('; ')}</td>
            <td><button class="action-btn" title="Edit"><i class="fa-solid fa-pen"></i></button><button class="action-btn" title="Delete"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `).join('');
}
function updateStudentSummary() {
    document.getElementById('totalStudents').innerText = studentsData.length;
    document.getElementById('executiveCount').innerText = studentsData.filter(s => (s.Memberships || []).some(m => m.Designation === 'Executive')).length;
}

// === EVENTS ===
async function fetchEvents() {
    try {
        const data = await safeFetchJSON('backend/events.php');
        eventsData = Array.isArray(data) ? data : [];
        updateEventSummary();
        renderEventRows(eventsData);
    } catch (error) {
        console.error('Error fetching events:', error);
        const tbody = document.getElementById('eventsBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="8">Error: ${error.message}</td></tr>`;
    }
}
function renderEventRows(data) {
    const tbody = document.getElementById('eventsBody');
    if (!tbody) return;
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="8">No events</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(item => `
        <tr data-id="${item.Event_ID}">
            <td>${item.Event_ID}</td>
            <td>${item.Title}</td>
            <td>${item.Date}</td>
            <td>${item.Venue}</td>
            <td>${item.HostClub || 'Unknown'}</td>
            <td>${item.Description || ''}</td>
            <td>${item.Volunteer_Count || 0}</td>
            <td>
                <button class="action-btn" data-id="${item.Event_ID}" title="Edit"><i class="fa-solid fa-pen"></i></button>
                <button class="action-btn" data-id="${item.Event_ID}" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}
function updateEventSummary() {
    document.getElementById('totalEvents').innerText = eventsData.length;
    document.getElementById('bookedEquipment').innerText = eventsData.reduce((s, e) => s + (parseInt(e.Equipment_Bookings) || 0), 0);
}

// === VOLUNTEERS ===
async function fetchVolunteers() {
    try {
        const data = await safeFetchJSON('backend/volunteers.php');
        volunteersData = Array.isArray(data) ? data : [];
        updateVolunteerSummary();
        renderVolunteerRows(volunteersData);
    } catch (error) {
        console.error('Error fetching volunteers:', error);
        const tbody = document.getElementById('volunteersBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="5">Error: ${error.message}</td></tr>`;
    }
}
function renderVolunteerRows(data) {
    const tbody = document.getElementById('volunteersBody');
    if (!tbody) return;
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="5">No volunteer records found.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(item => `
        <tr data-student="${item.Student_ID}" data-event="${item.Event_ID}">
            <td>${item.StudentName || item.Student_ID}</td>
            <td>${item.EventTitle || item.Event_ID}</td>
            <td>${item.Role}</td>
            <td>${item.Hours_Worked}</td>
            <td>
                <button class="action-btn" data-student="${item.Student_ID}" data-event="${item.Event_ID}" title="Edit"><i class="fa-solid fa-pen"></i></button>
                <button class="action-btn" data-student="${item.Student_ID}" data-event="${item.Event_ID}" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}
function updateVolunteerSummary() {
    document.getElementById('totalVolunteerHours').innerText = volunteersData.reduce((s, v) => s + parseFloat(v.Hours_Worked || 0), 0).toFixed(2);
    document.getElementById('totalVolunteerLogs').innerText = volunteersData.length;
}

// === BOOKINGS (Need) ===
async function fetchBookings() {
    try {
        const data = await safeFetchJSON('backend/bookings.php');
        bookingsData = Array.isArray(data) ? data : [];
        renderBookingRows(bookingsData);
        updateBookingSummary();
    } catch (error) {
        console.error('Error fetching bookings:', error);
        const tbody = document.getElementById('needBody');  
        if (tbody) tbody.innerHTML = `<tr><td colspan="6">Error: ${error.message}</td></tr>`;
    }
}
function renderBookingRows(data) {
    const tbody = document.getElementById('needBody');
    if (!tbody) return;
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="5">No bookings found.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(item => `
        <tr data-equip="${item.Equip_ID}" data-event="${item.Event_ID}">
            <td>${item.EquipmentName || item.Equip_ID}</td>
            <td>${item.EventTitle || item.Event_ID}</td>
            <td>${item.Borrow_Time}</td>
            <td>${item.Return_Time}</td>
            <td>
                <!-- Only Delete button, no Edit -->
                <button class="action-btn" data-equip="${item.Equip_ID}" data-event="${item.Event_ID}" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}
function updateBookingSummary() {
    const now = new Date();

    // Total bookings
    const totalEl = document.getElementById('totalneed');
    if (totalEl) totalEl.innerText = bookingsData.length;

    // Active bookings (borrow_time <= now <= return_time)
    const active = bookingsData.filter(b => {
        const borrow = new Date(b.Borrow_Time);
        const ret = new Date(b.Return_Time);
        return borrow <= now && now <= ret;
    });
    const activeEl = document.getElementById('activeBookings');
    if (activeEl) activeEl.innerText = active.length;

    // Returned bookings (return_time < now)
    const returned = bookingsData.filter(b => {
        const ret = new Date(b.Return_Time);
        return ret < now;
    });
    const returnedEl = document.getElementById('returnedBookings');
    if (returnedEl) returnedEl.innerText = returned.length;
}

// === MAINTENANCE ===
async function fetchMaintenance() {
    try {
        const data = await safeFetchJSON('backend/maintenance.php');
        maintenanceData = Array.isArray(data) ? data : [];
        renderMaintenanceRows(maintenanceData);
        updateMaintenanceSummary();
    } catch (error) {
        console.error('Error fetching maintenance:', error);
    }
}
function renderMaintenanceRows(data) {
    const tbody = document.getElementById('maintenanceBody');
    if (!tbody) return;
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="5">No maintenance logs found.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(item => `
        <tr data-equip="${item.Equip_ID}" data-log="${item.Log_ID}">
            <td>${item.EquipmentName || item.Equip_ID}</td>
            <td>${item.Date}</td>
            <td>${item.Description}</td>
            <td>$${parseFloat(item.Cost || 0).toFixed(2)}</td>
            <td>
                <button class="action-btn" data-equip="${item.Equip_ID}" data-log="${item.Log_ID}" title="Edit"><i class="fa-solid fa-pen"></i></button>
                <button class="action-btn" data-equip="${item.Equip_ID}" data-log="${item.Log_ID}" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}
function updateMaintenanceSummary() {
    const totalEl = document.getElementById('totalMaintenance');
    if (totalEl) totalEl.innerText = maintenanceData.length;

    const totalCost = maintenanceData.reduce((sum, m) => sum + parseFloat(m.Cost || 0), 0);
    const costEl = document.getElementById('totalMaintenanceCost');
    if (costEl) costEl.innerText = `$${totalCost.toFixed(2)}`;

    const thisMonth = new Date().getMonth();
    const thisYear = new Date().getFullYear();
    const monthCount = maintenanceData.filter(m => {
        const d = new Date(m.Date);
        return d.getMonth() === thisMonth && d.getFullYear() === thisYear;
    }).length;
    const monthEl = document.getElementById('maintenanceThisMonth');
    if (monthEl) monthEl.innerText = monthCount;
}

// === DASHBOARD ===
async function fetchDashboardStats() {
    try {
        const data = await safeFetchJSON('backend/analytics.php?type=overview_stats');
        
        document.getElementById('dashTotalClubs').innerText = data.total_clubs || 0;
        document.getElementById('dashTotalMembers').innerText = data.total_students || 0;
        document.getElementById('dashTotalEvents').innerText = data.total_events || 0;
        document.getElementById('dashTotalEquip').innerText = data.total_equipment || 0;
        document.getElementById('dashTotalHours').innerText = (data.total_volunteer_hours || 0).toFixed(1);
        document.getElementById('dashTotalBookings').innerText = data.total_bookings || 0;
        
        renderRecentActivity();
    } catch (error) {
        console.error('Error fetching dashboard stats:', error);
    }
}

function renderRecentActivity() {
    const tbody = document.getElementById('recentActivityBody');
    if (!tbody) return;
    
    const activities = [];
    
    eventsData.slice(0, 3).forEach(e => {
        activities.push({
            date: e.Date,
            type: 'Event',
            description: `"${e.Title}" at ${e.Venue}`,
            status: 'Upcoming'
        });
    });
    
    bookingsData.slice(0, 3).forEach(b => {
        activities.push({
            date: b.Borrow_Time,
            type: 'Booking',
            description: `${b.EquipmentName || 'Equipment'} booked for event`,
            status: b.Status || 'Confirmed'
        });
    });
    
    volunteersData.slice(0, 3).forEach(v => {
        activities.push({
            date: v.Join_Date || new Date().toISOString().split('T')[0],
            type: 'Volunteer',
            description: `${v.StudentName || 'Student'} logged ${v.Hours_Worked} hrs`,
            status: 'Completed'
        });
    });
    
    activities.sort((a, b) => new Date(a.date) - new Date(b.date));
    const recent = activities.slice(0, 10);
    
    if (recent.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem;">No recent activity.</td></tr>';
        return;
    }
    
    tbody.innerHTML = recent.map(item => `
        <tr>
            <td>${new Date(item.date).toLocaleString()}</td>
            <td><span class="status">${item.type}</span></td>
            <td>${item.description}</td>
            <td><span class="status ${item.status.toLowerCase()}">${item.status}</span></td>
        </tr>
    `).join('');
}

// === MEMBERSHIPS (Joine) ===
async function fetchMemberships() {
    try {
        const data = await safeFetchJSON('backend/joine.php');
        membershipsData = Array.isArray(data) ? data : [];
        renderMembershipRows(membershipsData);
        updateMembershipSummary();
    } catch (error) {
        console.error('Error fetching memberships:', error);
        const tbody = document.getElementById('joineBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="5">Error: ${error.message}</td></tr>`;
    }
}
function renderMembershipRows(data) {
    const tbody = document.getElementById('joineBody');
    if (!tbody) return;
    if (!data || !data.length) { tbody.innerHTML = '<tr><td colspan="5">No memberships</td></tr>'; return; }
    tbody.innerHTML = data.map(item => `
        <tr>
            <td>${item.StudentName || item.Student_ID}</td>
            <td>${item.ClubName || item.Club_ID}</td>
            <td>${item.Designation}</td>
            <td>${item.Join_Date}</td>
            <td><button class="action-btn" title="Edit"><i class="fa-solid fa-pen"></i></button><button class="action-btn" title="Delete"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `).join('');
}
function updateMembershipSummary() {
    const totalEl = document.getElementById('totalMemberships');
    if (totalEl) totalEl.innerText = membershipsData.length;
    const execEl = document.getElementById('executiveMemberships');
    if (execEl) execEl.innerText = membershipsData.filter(m => m.Designation === 'Executive').length;
    const activeEl = document.getElementById('activeMemberships');
    if (activeEl) activeEl.innerText = membershipsData.filter(m => m.Designation === 'General Member' || m.Designation === 'Volunteer').length;
}

// === BADGES ===
async function fetchBadges() {
    try {
        const data = await safeFetchJSON('backend/badges.php');
        badgesData = Array.isArray(data) ? data : [];
        renderBadges(badgesData);
        updateBadgeSummary();
    } catch (error) {
        console.error('Error fetching badges:', error);
        const grid = document.getElementById('badgesGrid');
        if (grid) grid.innerHTML = `<p style="padding:2rem;color:red;">Error: ${error.message}</p>`;
    }
}
function renderBadges(data) {
    const grid = document.getElementById('badgesGrid');
    if (!grid) return;
    if (!data || !data.length) { grid.innerHTML = '<p style="padding:2rem;">No badges</p>'; return; }
    grid.innerHTML = data.map(b => `
        <div class="badge-card ${b.Tier.toLowerCase()}">
            <div class="badge-header"><div class="badge-icon"><i class="fa-solid fa-medal"></i></div><div class="badge-info"><h3>${b.Name}</h3><span class="badge-tier">${b.Tier}</span></div></div>
            <p class="badge-description">${b.Description}</p>
            <div class="badge-requirements"><span class="badge-hours"><i class="fa-solid fa-clock"></i> ${b.Hours_Required} hours</span><span class="badge-earned-count">${b.Tier}</span></div>
        </div>
    `).join('');
}
function updateBadgeSummary() {
    const totalEl = document.getElementById('totalBadges');
    if (totalEl) totalEl.innerText = badgesData.length;
}
async function recalculateBadges(studentId = null) {
    // Only admin can recalculate badges
    if (currentUser.role !== 'admin') {
        alert('Permission denied');
        return;
    }

    const isAll = !studentId;                     // true if recalculating all students
    const action = isAll ? 'recalculate_all' : 'recalculate';
    const body = isAll ? {} : { student_id: studentId };

    // Confirm for all students (optional, but safe)
    if (isAll && !confirm('Recalculate badges for all students? This may take a moment.')) {
        return;
    }

    try {
        const response = await fetch(`backend/badges.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await response.json();

        if (data.success) {
            const message = isAll
                ? `Recalculation complete. ${data.total_earned} new badges awarded.`
                : `Badges recalculated: ${data.badges_earned} new badges earned.`;
            alert(message);
            fetchBadges(); // Refresh the badges display
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        console.error(e);
        alert('Network error');
    }
}

// === LEADERBOARD ===
async function fetchLeaderboard() {
    console.log('fetchLeaderboard called');
    try {
        const url = 'backend/badges.php?action=leaderboard&t=' + Date.now(); // Cache buster
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            renderLeaderboard(data.leaderboard);
        } else {
            console.error('Leaderboard error:', data.error);
        }
    } catch (error) {
        console.error('Error fetching leaderboard:', error);
    }
}
function renderLeaderboard(data) {
    const tbody = document.getElementById('leaderboardBody') || document.getElementById('leaderboardBodyMain');
    if (!tbody) return;
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="7">No leaderboard data.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map((item, index) => {
        const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';
        // ✅ Parse numeric values safely
        const totalHours = parseFloat(item.Total_Hours) || 0;
        const eventsCount = parseInt(item.Events_Count) || 0;
        const badgesEarned = parseInt(item.Badges_Earned) || 0;
        const highestTier = item.Highest_Tier || 'None';
        return `
            <tr>
                <td><span class="rank-medal">${medal}</span> ${index+1}</td>
                <td>${item.Name || 'Unknown'}</td>
                <td>${totalHours.toFixed(2)}</td>
                <td>${eventsCount}</td>
                <td>${badgesEarned}</td>
                <td>${highestTier}</td>
            </tr>
        `;
    }).join('');
}

// ========== CRUD ==========
function openEditModal(section, identifier) {
    // Check permissions
    if (currentUser.role !== 'admin') {
        alert('You do not have permission to edit this item.');
        return;
    }

    let data = null;
    let endpoint = '';
    let idForForm = identifier;

    // Debug logging
    console.log('openEditModal called with:', { section, identifier });

    // ----- FIND THE DATA -----
    switch (section) {
        case 'equipment':
            data = equipmentData.find(i => i.Equip_ID == identifier);
            endpoint = 'equipment.php';
            break;

        case 'clubs':
            data = clubsData.find(i => i.Club_ID == identifier);
            endpoint = 'clubs.php';
            break;

        case 'students':
            data = studentsData.find(i => i.Student_ID == identifier);
            endpoint = 'students.php';
            break;

        case 'events':
            data = eventsData.find(i => i.Event_ID == identifier);
            endpoint = 'events.php';
            break;

        case 'volunteers':
            data = volunteersData.find(v => v.Student_ID == identifier.student_id && v.Event_ID == identifier.event_id);
            endpoint = 'volunteers.php';
            break;

        case 'bookings':
            // Composite key: equip_id + event_id
            data = bookingsData.find(i => 
                i.Equip_ID == identifier.equip_id && 
                i.Event_ID == identifier.event_id
            );
            endpoint = 'bookings.php';
            idForForm = identifier;
            break;

        case 'maintenance':
            // Composite key: equip_id + log_id
            console.log('Maintenance data:', maintenanceData);
            console.log('Looking for:', identifier);
            data = maintenanceData.find(i => 
                i.Equip_ID == identifier.equip_id && 
                i.Log_ID == identifier.log_id
            );
            endpoint = 'maintenance.php';
            idForForm = identifier;
            break;

        case 'memberships':
            data = membershipsData.find(m => m.Student_ID == identifier.student_id && m.Club_ID == identifier.club_id);
            endpoint = 'joine.php';
            // Store both IDs as JSON string
            const ids = { student_id: identifier.student_id, club_id: identifier.club_id };
            document.getElementById('crudForm').dataset.id = JSON.stringify(ids);
            break;

        default:
            alert('Unknown section: ' + section);
            return;
    }

    // ----- CHECK IF DATA WAS FOUND -----
    if (!data) {
        console.error('Data not found for:', { section, identifier });
        alert('Item not found. Please refresh the page.');
        return;
    }

    console.log('Found data:', data);

    // ----- GENERATE FORM HTML -----
    const formHtml = generateFormHtml(section, data);

    // ----- SETUP THE FORM -----
    const form = document.getElementById('crudForm');
    form.innerHTML = formHtml;
    form.dataset.section = section;
    form.dataset.id = typeof idForForm === 'object' ? JSON.stringify(idForForm) : idForForm;
    form.dataset.endpoint = endpoint;
    form.dataset.method = 'PUT';

    // ----- SET MODAL TITLE -----
    const titleMap = {
        equipment: 'Edit Equipment',
        clubs: 'Edit Club',
        students: 'Edit Member',
        events: 'Edit Event',
        volunteers: 'Edit Volunteer Log',
        bookings: 'Edit Booking',
        maintenance: 'Edit Maintenance Log',
        memberships: 'Edit Membership'
    };
    document.getElementById('modalTitle').textContent = titleMap[section] || 'Edit Item';

    // ----- SHOW THE MODAL -----
    document.getElementById('crudModal').style.display = 'flex';
}

function generateFormHtml(section, data) {
    // Role options for memberships
    const roleOptions = ['General Member', 'Volunteer', 'Executive'];

    // Status options for equipment
    const statusOptions = ['Available', 'In-Use', 'Damaged', 'Maintenance'];

    // Equipment type options
    const typeOptions = ['Camera', 'Projector', 'Microphone', 'Laptop', 'Speaker', 'Other'];

    // Booking status options
    const bookingStatusOptions = ['Confirmed', 'Completed', 'Cancelled'];

    switch (section) {
        // =========================================
        // EQUIPMENT
        // =========================================
        case 'equipment':
            const currentStatus = data.Status || 'Available';
            let statusHtml = '';

            if (currentStatus === 'Available') {
                statusHtml = `
                    <select name="status" required>
                        <option value="Available" ${data.Status === 'Available' ? 'selected' : ''}>Available</option>
                        <option value="Damaged" ${data.Status === 'Damaged' ? 'selected' : ''}>Damaged</option>
                    </select>
                    <small style="color: var(--gray-500); display: block; margin-top: 0.25rem;">
                        Mark as <strong>Damaged</strong> to log maintenance later.
                    </small>
                `;
            } else {
                statusHtml = `
                    <input type="text" value="${data.Status}" readonly style="background:#f0f0f0;color:#666;">
                    <input type="hidden" name="status" value="${data.Status}">
                    <small style="color: var(--gray-500); display: block; margin-top: 0.25rem;">
                        Status cannot be changed here. Only <strong>Available</strong> equipment can be marked as <strong>Damaged</strong>.
                        ${data.Status === 'Damaged' ? 'To repair, log maintenance.' : ''}
                    </small>
                `;
            }

            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Equipment ID</label>
                        <input type="text" value="${data.Equip_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" value="${data.Name || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <input type="text" value="${data.Type || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                    <div class="form-group">
                        <label>Owner Club ID</label>
                        <input type="text" value="${data.Owner_Club_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        ${statusHtml}
                    </div>
                    <div class="form-group">
                        <label>Purchase Date</label>
                        <input type="text" value="${data.Purchase_Date || 'N/A'}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                </div>
                <input type="hidden" name="name" value="${data.Name || ''}">
                <input type="hidden" name="type" value="${data.Type || ''}">
                <input type="hidden" name="owner_club_id" value="${data.Owner_Club_ID || ''}">
                <input type="hidden" name="purchase_date" value="${data.Purchase_Date || ''}">
            `;

        // =========================================
        // CLUBS
        // =========================================
        case 'clubs':
            const emails = data.ContactEmails || '';
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Club ID</label>
                        <input type="text" value="${data.Club_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                    <div class="form-group">
                        <label>Club Name</label>
                        <input type="text" name="name" value="${data.Name || ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" value="${data.Department || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Office Room</label>
                        <input type="text" name="office_room" value="${data.Office_Room || ''}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Club Emails</label>
                        <input type="text" name="emails" value="${emails}" placeholder="Enter emails separated by commas">
                    </div>
                </div>
            `;

        // =========================================
        // STUDENTS / MEMBERS
        // =========================================
        case 'students':
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" value="${data.Student_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="${data.Name || ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="${data.Email || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current" value="">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Street</label>
                        <input type="text" name="street" value="${data.Street || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Sub-district</label>
                        <input type="text" name="sub_district" value="${data.Sub_district || ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>District</label>
                        <input type="text" name="district" value="${data.District || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Numbers</label>
                        <input type="text" name="phones" value="${(data.Phone_Numbers || []).join(', ')}" placeholder="Comma separated">
                    </div>
                </div>
            `;

        // =========================================
        // EVENTS
        // =========================================
        case 'events':
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Event ID</label>
                        <input type="text" value="${data.Event_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" value="${data.Title || ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" value="${data.Date ? data.Date.split('T')[0] : ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Venue</label>
                        <input type="text" name="venue" value="${data.Venue || ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Club ID</label>
                        <input type="number" name="primary_club_id" value="${data.Primary_Club_ID || ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3">${data.Description || ''}</textarea>
                    </div>
                </div>
            `;

        // =========================================
        // VOLUNTEERS
        // =========================================
        case 'volunteers':
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" value="${data.Student_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                        <input type="hidden" name="student_id" value="${data.Student_ID || ''}">
                    </div>
                    <div class="form-group">
                        <label>Event ID</label>
                        <input type="text" value="${data.Event_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                        <input type="hidden" name="event_id" value="${data.Event_ID || ''}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="${data.Role || ''}" readonly style="background:#f0f0f0;color:#666;">
                        <input type="hidden" name="role" value="${data.Role || ''}">
                    </div>
                    <div class="form-group">
                        <label>Hours Worked</label>
                        <input type="number" name="hours_worked" step="0.5" min="0.5" max="24" value="${data.Hours_Worked}" required>
                        <!-- ^ Use data.Hours_Worked directly (no fallback) -->
                        <small style="color: var(--gray-500); display: block; margin-top: 0.25rem;">
                            Hours must be between <strong>0.5 and 24</strong>.
                        </small>
                    </div>
                </div>
            `;

        // =========================================
        // BOOKINGS (Need table)
        // =========================================
        case 'bookings':
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Equipment ID</label>
                        <input type="number" name="equip_id" value="${data.Equip_ID || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Event ID</label>
                        <input type="number" name="event_id" value="${data.Event_ID || ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Borrow Time</label>
                        <input type="datetime-local" name="borrow_time" value="${data.Borrow_Time ? data.Borrow_Time.slice(0, 16) : ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Return Time</label>
                        <input type="datetime-local" name="return_time" value="${data.Return_Time ? data.Return_Time.slice(0, 16) : ''}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            ${bookingStatusOptions.map(s => 
                                `<option value="${s}" ${data.Status === s ? 'selected' : ''}>${s}</option>`
                            ).join('')}
                        </select>
                    </div>
                </div>
            `;

        // =========================================
        // MAINTENANCE
        // =========================================
        case 'maintenance':
            // Generate dropdown for equipment (only those in maintenance)
            let equipOptions = '<option value="">Select Equipment</option>';
            // Show all equipment or filter? For edit, show the current one
            equipmentData.forEach(e => {
                equipOptions += `<option value="${e.Equip_ID}" ${data.Equip_ID == e.Equip_ID ? 'selected' : ''}>${e.Equip_ID} - ${e.Name}</option>`;
            });

            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Equipment ID</label>
                        <input type="text" value="${data.Equip_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                    <div class="form-group">
                        <label>Log ID</label>
                        <input type="text" value="${data.Log_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" value="${data.Date ? data.Date.split('T')[0] : ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Cost ($)</label>
                        <input type="number" name="cost" step="0.01" min="0" value="${data.Cost || '0.00'}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" required>${data.Description || ''}</textarea>
                    </div>
                </div>
            `;

        // =========================================
        // MEMBERSHIPS (Joine)
        // =========================================
        case 'memberships':
            const roleOptions = ['General Member', 'Volunteer', 'Executive'];
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" value="${data.Student_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                        <input type="hidden" name="student_id" value="${data.Student_ID || ''}">
                    </div>
                    <div class="form-group">
                        <label>Club ID</label>
                        <input type="text" value="${data.Club_ID || ''}" readonly style="background:#f0f0f0;color:#666;">
                        <input type="hidden" name="club_id" value="${data.Club_ID || ''}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Join Date</label>
                        <input type="text" value="${data.Join_Date || ''}" readonly style="background:#f0f0f0;color:#666;">
                        <input type="hidden" name="join_date" value="${data.Join_Date || ''}">
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation" required>
                            ${roleOptions.map(r => 
                                `<option value="${r}" ${data.Designation === r ? 'selected' : ''}>${r}</option>`
                            ).join('')}
                        </select>
                        <small style="color: var(--gray-500); display: block; margin-top: 0.25rem;">
                            Only the <strong>Designation</strong> can be changed.
                        </small>
                    </div>
                </div>
            `;

        default:
            return '<p style="color:red;">Form not available for this section.</p>';
    }
}

// === ADD FUNCTIONS ===
function addEquipment() {
    if (currentUser.role !== 'admin') { alert('Permission denied'); return; }

    const formHtml = `
        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" required>
                    <option value="Camera">Camera</option>
                    <option value="Projector">Projector</option>
                    <option value="Microphone">Microphone</option>
                    <option value="Laptop">Laptop</option>
                    <option value="Speaker">Speaker</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status" required>
                    <option value="Available">Available</option>
                    <option value="In-Use">In-Use</option>
                    <option value="Damaged">Damaged</option>
                    <option value="Maintenance">Maintenance</option>
                </select>
            </div>
            <div class="form-group">
                <label>Owner Club ID</label>
                <input type="number" name="owner_club_id" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Purchase Date</label>
                <input type="date" name="purchase_date">
            </div>
        </div>
    `;

    showAddForm('equipment', 'Add Equipment', formHtml, 'POST', 'equipment.php');
}
function addClub() {
    if (currentUser.role !== 'admin') {
        alert('Permission denied');
        return;
    }

    const formHtml = `
        <div class="form-row">
            <div class="form-group">
                <label>Club Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Office Room</label>
                <input type="text" name="office_room">
            </div>
            <div class="form-group">
                <label>Club Emails</label>
                <input type="text" name="emails" placeholder="Enter emails separated by commas" required>
            </div>
        </div>
    `;

    showAddForm('clubs', 'Add Club', formHtml, 'POST', 'clubs.php');
}
function addStudent() {
    if (currentUser.role !== 'admin') { alert('Permission denied'); return; }
    showAddForm('students', 'Add Member', generateFormHtml('students', {}), 'POST', 'students.php');
}
function addEvent() {
    if (currentUser.role !== 'admin') { alert('Permission denied'); return; }
    showAddForm('events', 'Add Event', generateFormHtml('events', {}), 'POST', 'events.php');
}
function addBooking() {
    if (currentUser.role !== 'admin') {
        alert('Permission denied');
        return;
    }

    // Only show equipment that is Available
    const availableEquipment = equipmentData.filter(e => e.Status === 'Available');

    if (availableEquipment.length === 0) {
        alert('No available equipment to book.');
        return;
    }

    let equipOptions = '<option value="">Select Equipment</option>';
    availableEquipment.forEach(e => {
        equipOptions += `<option value="${e.Equip_ID}">${e.Equip_ID} - ${e.Name}</option>`;
    });

    let eventOptions = '<option value="">Select Event</option>';
    eventsData.forEach(e => {
        eventOptions += `<option value="${e.Event_ID}">${e.Title} - ${e.Date}</option>`;
    });

    const formHtml = `
        <div class="form-row">
            <div class="form-group">
                <label>Equipment</label>
                <select name="equip_id" required>
                    ${equipOptions}
                </select>
            </div>
            <div class="form-group">
                <label>Event</label>
                <select name="event_id" required>
                    ${eventOptions}
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Borrow Time</label>
                <input type="datetime-local" name="borrow_time" required>
            </div>
            <div class="form-group">
                <label>Return Time</label>
                <input type="datetime-local" name="return_time" required>
            </div>
        </div>
        <div style="padding: 1rem; background: var(--gray-50); border-radius: 8px; margin-top: 1rem;">
            <p style="margin: 0; color: var(--gray-600); font-size: 0.875rem;">
                <i class="fa-solid fa-info-circle"></i>
                Equipment will be marked as <strong>In-Use</strong> when booked, and back to <strong>Available</strong> when the booking is deleted.
            </p>
        </div>
    `;

    showAddForm('bookings', 'Add Booking', formHtml, 'POST', 'bookings.php');
}
function addMaintenance() {
    if (currentUser.role !== 'admin') {
        alert('Permission denied');
        return;
    }

    // Only show equipment that is currently Damaged
    const damagedEquipment = equipmentData.filter(e => e.Status === 'Damaged');

    if (damagedEquipment.length === 0) {
        alert('No damaged equipment found to log maintenance for.');
        return;
    }

    let equipOptions = '<option value="">Select Equipment</option>';
    damagedEquipment.forEach(e => {
        equipOptions += `<option value="${e.Equip_ID}">${e.Equip_ID} - ${e.Name}</option>`;
    });

    const formHtml = `
        <div class="form-row">
            <div class="form-group">
                <label>Equipment</label>
                <select name="equip_id" required>
                    ${equipOptions}
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" required></textarea>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Cost ($)</label>
                <input type="number" name="cost" step="0.01" min="0" required>
            </div>
        </div>
    `;

    showAddForm('maintenance', 'Log Maintenance', formHtml, 'POST', 'maintenance.php');
}
function addMembership() {
    if (currentUser.role !== 'admin') {
        alert('Permission denied');
        return;
    }

    // Generate dropdown options for students and clubs
    let studentOptions = '<option value="">Select Student</option>';
    studentsData.forEach(s => {
        studentOptions += `<option value="${s.Student_ID}">${s.Student_ID} - ${s.Name}</option>`;
    });

    let clubOptions = '<option value="">Select Club</option>';
    clubsData.forEach(c => {
        clubOptions += `<option value="${c.Club_ID}">${c.Club_ID} - ${c.Name}</option>`;
    });

    const roleOptions = ['General Member', 'Volunteer', 'Executive'];
    let roleHtml = roleOptions.map(r => `<option value="${r}">${r}</option>`).join('');

    const formHtml = `
        <div class="form-row">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    ${studentOptions}
                </select>
            </div>
            <div class="form-group">
                <label>Club</label>
                <select name="club_id" required>
                    ${clubOptions}
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Designation</label>
                <select name="designation" required>
                    ${roleHtml}
                </select>
            </div>
            <div class="form-group">
                <label>Join Date</label>
                <input type="date" name="join_date" required>
            </div>
        </div>
    `;

    showAddForm('memberships', 'Add Membership', formHtml, 'POST', 'joine.php');
}
function addVolunteer() {
    if (currentUser.role !== 'admin') {
        alert('Permission denied');
        return;
    }

    // Only show students who are already signed up
    if (!studentsData || studentsData.length === 0) {
        alert('No students available. Please add a student first.');
        return;
    }

    let studentOptions = '<option value="">Select Student</option>';
    studentsData.forEach(s => {
        studentOptions += `<option value="${s.Student_ID}">${s.Student_ID} - ${s.Name}</option>`;
    });

    let eventOptions = '<option value="">Select Event</option>';
    eventsData.forEach(e => {
        eventOptions += `<option value="${e.Event_ID}">${e.Title} - ${e.Date}</option>`;
    });

    const formHtml = `
        <div class="form-row">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    ${studentOptions}
                </select>
            </div>
            <div class="form-group">
                <label>Event</label>
                <select name="event_id" required>
                    ${eventOptions}
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Role</label>
                <input type="text" name="role" placeholder="e.g., Setup Crew, Registration" required>
            </div>
            <div class="form-group">
                <label>Hours Worked</label>
                <input type="number" name="hours_worked" step="0.5" min="0.5" max="24" required>
            </div>
        </div>
        <div style="padding: 1rem; background: var(--gray-50); border-radius: 8px; margin-top: 1rem;">
            <p style="margin: 0; color: var(--gray-600); font-size: 0.875rem;">
                <i class="fa-solid fa-info-circle"></i>
                Only existing students can be added as volunteers.
            </p>
        </div>
    `;

    showAddForm('volunteers', 'Add Volunteer', formHtml, 'POST', 'volunteers.php');
}
function showAddForm(section, title, formHtml, method, endpoint) {
    document.getElementById('crudForm').innerHTML = formHtml;
    document.getElementById('crudForm').dataset.section = section;
    document.getElementById('crudForm').dataset.endpoint = endpoint;
    document.getElementById('crudForm').dataset.method = method;
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('crudModal').style.display = 'flex';
}

// === FORM SUBMIT & DELETE ===
document.getElementById('crudForm').addEventListener('submit', handleFormSubmit);

function handleFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    const section = form.dataset.section;
    const id = form.dataset.id;
    const endpoint = form.dataset.endpoint;
    const method = form.dataset.method;

    // For students, convert phones string to array
    if (section === 'students' && data.phones) {
        data.phones = data.phones.split(',').map(p => p.trim()).filter(p => p);
    }

    // Build URL – handle composite IDs
    let url = `backend/${endpoint}`;
    if (id) {
        if (id.startsWith('{')) {
            try {
                const ids = JSON.parse(id);
                const params = new URLSearchParams();
                Object.keys(ids).forEach(key => params.append(key, ids[key]));
                url += '?' + params.toString();
            } catch (e) {
                url += `?id=${id}`;
            }
        } else {
            url += `?id=${id}`;
        }
    }

    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.text())   // read as text first
    .then(text => {
        console.log('Raw server response:', text);
        try {
            const result = JSON.parse(text);
            if (result.success) {
                closeModal();
                fetchAllData();
                alert(result.message || 'Operation successful!');
            } else {
                alert('Error: ' + (result.error || result.message || 'Unknown error'));
            }
        } catch (e) {
            alert('Server returned invalid JSON:\n' + text.substring(0, 300));
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        alert('Network error: ' + err.message);
    });
}

function handleDelete(section, identifier, row) {
    // ----- Permission check -----
    if (currentUser.role !== 'admin') {
        alert('Permission denied');
        return;
    }

    // ----- Confirmation -----
    if (!confirm(`Are you sure you want to delete this ${section}? This action cannot be undone.`)) {
        return;
    }

    // ----- Build the DELETE URL -----
    let url = '';
    const base = 'backend/';

    switch (section) {
        // ---------- SINGLE-KEY TABLES ----------
        case 'equipment':
            url = `${base}equipment.php?id=${identifier}`;
            break;

        case 'clubs':
            url = `${base}clubs.php?id=${identifier}`;
            break;

        case 'students':
            url = `${base}students.php?id=${identifier}`;
            break;

        case 'events':
            url = `${base}events.php?id=${identifier}`;
            break;

        // ---------- COMPOSITE-KEY TABLES ----------
        // identifier is an object: { student_id, event_id }
        case 'volunteers':
            url = `${base}volunteers.php?student_id=${identifier.student_id}&event_id=${identifier.event_id}`;
            break;

        // identifier is an object: { equip_id, event_id }
        case 'bookings':
            url = `${base}bookings.php?equip_id=${identifier.equip_id}&event_id=${identifier.event_id}`;
            break;

        // identifier is an object: { equip_id, log_id }
        case 'maintenance':
            url = `${base}maintenance.php?equip_id=${identifier.equip_id}&log_id=${identifier.log_id}`;
            break;

        // identifier is an object: { student_id, club_id }
        case 'memberships':
            url = `${base}joine.php?student_id=${identifier.student_id}&club_id=${identifier.club_id}`;
            break;

        default:
            alert('Unknown section: ' + section);
            return;
    }

    // ----- Execute DELETE request -----
    fetch(url, { method: 'DELETE' })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Remove the row from the table
                if (row && row.remove) {
                    row.remove();
                } else {
                    // Fallback: reload the page
                    fetchAllData();
                }

                // Refresh all data to update KPIs and lists
                fetchAllData();

                alert('Deleted successfully!');
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            alert('Network error: ' + err.message);
        });
}

function closeModal() {
    document.getElementById('crudModal').style.display = 'none';
    document.getElementById('crudForm').reset();
}

// ========== ANALYTICS ==========
async function loadEquipmentUtilization() {
    try {
        const data = await safeFetchJSON('backend/analytics.php?type=equipment_utilization');
        renderAnalytics('equipUtilBody', data, ['Name','Type','OwnerClub','Total_Bookings','Avg_Hours_Booked','Utilization_Level']);
    } catch (e) { console.error(e); }
}
async function loadEventSuccess() {
    try {
        const data = await safeFetchJSON('backend/analytics.php?type=event_success');
        renderAnalytics('eventSuccessBody', data, ['Title','Date','Primary_Club','Volunteers_Count','Equipment_Used','Event_Scale','Success_Score']);
    } catch (e) { console.error(e); }
}
async function loadStudentEngagement() {
    try {
        const data = await safeFetchJSON('backend/analytics.php?type=student_engagement');
        renderAnalytics('engagementBody', data, ['Name','Total_Hours','Events_Volunteered','Clubs_Joined','Is_Executive','Engagement_Score','Engagement_Category']);
    } catch (e) { console.error(e); }
}
async function loadBookingPatterns() {
    try {
        const data = await safeFetchJSON('backend/analytics.php?type=booking_patterns');
        renderAnalytics('bookingPatternsBody', data, ['Day_Of_Week','Hour_Of_Day','Equipment_Type','Booking_Count','Avg_Duration_Hours','Demand_Level']);
    } catch (e) { console.error(e); }
}
function renderAnalytics(tbodyId, data, headers) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    if (!data || !data.length) { tbody.innerHTML = `<tr><td colspan="${headers.length}">No data</td></tr>`; return; }
    tbody.innerHTML = data.map(row => `<tr>${headers.map(h => `<td>${row[h] ?? ''}</td>`).join('')}</tr>`).join('');
}

// Make functions globally available
window.loadEquipmentUtilization = loadEquipmentUtilization;
window.loadEventSuccess = loadEventSuccess;
window.loadStudentEngagement = loadStudentEngagement;
window.loadBookingPatterns = loadBookingPatterns;
window.switchSection = switchSection;
window.closeModal = closeModal;
window.fetchLeaderboard = fetchLeaderboard;