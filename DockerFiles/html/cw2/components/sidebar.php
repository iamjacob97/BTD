<nav id="sidebar">
    <ul>
        <li>
            <button onclick="toggleSidebar()" id="toggle-btn">
            <span class="logo" style="color:red">BTD</span>
            </button>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/>
                </svg>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
            <a href="profile.php">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/>
                </svg>
                <span>Profile</span>
            </a>
        </li>
        <li>
            <button onclick="toggleSubMenu(this)" class="dropdown-btn">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
                </svg>
                <span>Search</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/>
                </svg>
            </button>
            <ul class="sub-menu">
                <div>
                    <li><a href="people.php">People</a></li>
                    <li><a href="vehicles.php">Vehicles</a></li>
                    <li><a href="incidents.php">Incidents</a></li>
                </div>
            </ul>
        </li>
        <li>
            <button onclick="toggleSubMenu(this)" class="dropdown-btn">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/>
                </svg>
                <span>New</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/>
                </svg>
            </button>
            <ul class="sub-menu">
                <div>
                    <li><a href="create_person.php">Person</a></li>
                    <li><a href="create_vehicle.php">Vehicle</a></li>
                    <li><a href="new_incident.php">Incident</a></li>
                    <li><a href="new_ownership.php">Ownership</a></li>
                </div>
            </ul>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li>
            <button onclick="toggleSubMenu(this)" class="dropdown-btn">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed">
                    <path d="M686-132 444-376q-20 8-40.5 12t-43.5 4q-100 0-170-70t-70-170q0-36 10-68.5t28-61.5l146 146 72-72-146-146q29-18 61.5-28t68.5-10q100 0 170 70t70 170q0 23-4 43.5T584-516l244 242q12 12 12 29t-12 29l-84 84q-12 12-29 12t-29-12Zm29-85 27-27-256-256q18-20 26-46.5t8-53.5q0-60-38.5-104.5T386-758l74 74q12 12 12 28t-12 28L332-500q-12 12-28 12t-28-12l-74-74q9 57 53.5 95.5T360-440q26 0 52-8t47-25l256 256ZM472-488Z"/>
                </svg>
                <span>Admin Tools</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/>
                </svg>
            </button>
            <ul class="sub-menu">
                <div>
                    <li><a href="create_user.php">New User</a></li>
                    <li><a href="new_offence.php">New Offence</a></li>
                    <li><a href="new_fine.php">New Fine</a></li>
                    <li><a href="audit.php">Audit Trail</a></li>
                </div>
            </ul>
        </li>
        <?php endif; ?>
        <li>
            <a href="logout.php">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="#e8eaed">
                    <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-55-58 102-102H360v-80h327L585-622l55-58 200 200-200 200Z"/>
                </svg>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</nav>