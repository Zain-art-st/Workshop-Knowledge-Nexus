<?php
session_start();
include "db.php";


// Authentication guard check
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Auto grab matric number from student_profiles using a LEFT JOIN
$query =
    "SELECT u.*, s.matric_number FROM users u LEFT JOIN student_profiles s ON u.id = s.user_id WHERE u.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id); //"i" stands for integer type
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

$username = $user["username"] ?? $_SESSION["username"];
$bio = $user["bio"] ?? "";
$user_type = $user["user_type"] ?? "student"; //Detects 'student or graduate'

$matric_no = !empty($user["matric_number"])
    ? $user["matric_number"]
    : "Alumni Member"; //Fallback to "Alumni Member" if matric_number is not set (for graduates)
$role = ucfirst($user_type); //Default role if matric number is not valid or not set

//Initialize empty variables for graduate details
$job_title = "";
$company = "";
$education_level = "";
$field_of_study = "";
$linkedin_url = "";

if ($user_type === "graduate") {
    //Fetch extra graduate info
    $grad_query =
        "SELECT job_title, company, education_level, graduation_year, field_of_study, linkedin_url FROM graduate_profiles WHERE user_id = ?";
    $grad_stmt = mysqli_prepare($conn, $grad_query);
    mysqli_stmt_bind_param($grad_stmt, "i", $user_id);
    mysqli_stmt_execute($grad_stmt);
    $grad_result = mysqli_stmt_get_result($grad_stmt);

    if ($grad_result && mysqli_num_rows($grad_result) > 0) {
        $grad_row = mysqli_fetch_assoc($grad_result);
        $job_title = $grad_row["job_title"] ?? "";
        $company = $grad_row["company"] ?? "";
        $education_level = $grad_row["education_level"] ?? "";
        $graduation_year = $grad_row["graduation_year"] ?? "";
        $field_of_study = $grad_row["field_of_study"] ?? "";
        $linkedin_url = $grad_row["linkedin_url"] ?? "";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - ScholarSpace</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    
    <div class="stars-bg"></div>
    <div class="sunset-bg"></div>

    <nav class="navbar">
        <a href="dashboard.php" class="nav-logo">ScholarSpace</a>
        <div class="nav-right">
            <span class="nav-welcome">Welcome, <strong><?php echo htmlspecialchars(
                $username,
            ); ?></strong></span>
            <label for="menu-toggle-check" class="profile-avatar">
                <span><?php echo strtoupper(substr($username, 0, 1)); ?></span>
            </label>
            <input type="checkbox" id="menu-toggle-check" class="profile-toggle-checkbox">
            <div class="profile-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">My Profile</a>
                <hr>
                <a href="logout.php" style="color: var(--danger);">Logout</a>
            </div>
        </div>
    </nav>

    <div class="page-wrapper">
        <div class="layout-container">
            
            <main class="profile-main-grid">
                
                <div class="card">
                    <div class="card-body profile-hero-card">
                        <div class="avatar-wrapper">
                            <div class="main-pfp profile-avatar" style="width:100%; height:100%; font-size:36px;">
                                <?php echo strtoupper(
                                    substr($username, 0, 1),
                                ); ?>
                            </div>
                            <button class="edit-pfp-overlay-btn" onclick="openModal('settingsModal')">✏️</button>
                        </div>
                        <h2><?php echo htmlspecialchars($username); ?></h2>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 4px 0 12px;">
                            <?php echo $matric_no
                                ? htmlspecialchars($matric_no)
                                : "No Matric Number Set"; ?>
                        </p>
                        <span class="status-badge <?php echo strtolower(
                            $role,
                        ); ?>">
                           Verified As <?php echo $role; ?>
                        </span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="section-card-title">
                            <h3>About / Biography</h3>
                            <button class="card-action-trigger" onclick="openModal('settingsModal')">✏️</button>
                        </div>
                        <p style="font-size: 14px; line-height: 1.6; color: <?php echo $bio
                            ? "var(--text-main)"
                            : "var(--text-muted)"; ?>;">
                            <?php echo $bio
                                ? nl2br(htmlspecialchars($bio))
                                : "Write a bio to tell other students about your interests, skills, or tech stacks..."; ?>
                        </p>
                    </div>
                </div>

                <?php if ($user_type === "graduate"): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="section-card-title">
                                <h3>Professional Details</h3>
                                <button class="card-action-trigger" onclick="openModal('settingsModal')">✏️</button>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 14px; margin-top: 12px;">
                                <div>
                                    <strong style="color: var(--text-muted);">Education Level:</strong>
                                    <p style="margin: 4px 0 12px;"><?php echo htmlspecialchars(
                                        $education_level ?: "Not Set",
                                    ); ?></p>
                                </div>
                                <div>
                                    <strong style="color: var(--text-muted);">Field of Study:</strong>
                                    <p style="margin: 4px 0 12px;"><?php echo htmlspecialchars(
                                        $field_of_study ?: "Not Set",
                                    ); ?></p>
                                </div>
                            </div>
                            <div>
                                <strong style="color: var(--text-muted);">Current Placement:</strong>
                                <p style="margin: 4px 0 12px;">
                                    <?php if (
                                        !empty($job_title) &&
                                        !empty($company)
                                    ) {
                                        echo htmlspecialchars(
                                            "$job_title at $company",
                                        );
                                    } else {
                                        echo htmlspecialchars(
                                            $job_title ?:
                                            ($company ?:
                                            "Not Set"),
                                        );
                                    } ?>
                                </p>
                            </div>
                            <div> 
                                <strong style="color: var(--text-muted);">Graduation Year:</strong>
                                <p style="margin: 4px 0 12px;"><?php echo htmlspecialchars(
                                    $graduation_year ?: "Not Set",
                                ); ?></p>
                            </div>

                            <?php if (!empty($linkedin_url)): ?>
                                <div style="margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px;">
                                    <strong style="color: var(--text-muted);">LinkedIn Profile:</strong>
                                    <p style="margin: 4px 0 0;">
                                        <a href="<?php echo htmlspecialchars(
                                            $linkedin_url,
                                        ); ?>" target="_blank" style="color: #007bff; text-decoration: none;">🔗 View LinkedIn Profile</a>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="section-card-title">
                            <h3>Projects</h3>
                            <button class="card-action-trigger" onclick="openModal('projectModal')">+</button>
                        </div>
                        <div id="project-list-target">
                            <p style="font-size: 13px; color: var(--text-muted);">No projects pinned to your profile yet.</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="section-card-title">
                            <h3>Skills</h3>
                            <button class="card-action-trigger" onclick="openModal('skillsModal')">+</button>
                        </div>
                        <div id="skills-list-target">
                            <p style="font-size: 13px; color: var(--text-muted);">No core skill sets declared.</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="section-card-title">
                            <h3>Experiences</h3>
                            <button class="card-action-trigger" onclick="openModal('experienceModal')">+</button>
                        </div>
                        <div id="experience-list-target">
                            <p style="font-size: 13px; color: var(--text-muted);">No professional history added yet.</p>
                        </div>
                    </div>
                </div>

            </main> <aside class="right-sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-card-header">Tips</div>
                    <div style="padding: 16px; font-size: 13px; color: var(--text-muted); line-height: 1.5;">
                        <?php if ($user_type === "student"): ?>
                            Keeping your matrix number, project repositories and technical background fully updated helps your peers team up with you for hackathons!
                        <?php else: ?>
                            Keeping your current employment status, industry fields and project history updated helps student developers look up to your profile for career guidance!
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

        </div>
    </div>

    <div id="settingsModal" class="modal-wrapper">
        <div class="modal-box">
            <div class="modal-box-header">
                <h3>Edit Profile Basics</h3>
                <button class="modal-close-trigger" onclick="handleSystemClose('settingsModal')">&times;</button>
            </div>

    <form action="process_profile.php" method="POST" class="profile-input-form">
    <div class="form-group">
        <label>Display Username</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars(
            $username,
        ); ?>" required>
    </div>

    <?php if ($user_type === "student"): ?>
    <div class="form-group">
        <label>Matric Number <span style="font-size: 11px; color: var(--text-muted);">(Locked)</span></label>
        <input type="text" name="matric_number" value="<?php echo htmlspecialchars(
            $matric_no,
        ); ?>" readonly style="background: #14141e; color: #a0a0b0; cursor: not-allowed; border: 1px solid rgba(255,255,255,0.05);">
    </div>
<?php elseif ($user_type === "graduate"): ?>
    <div class="form-group">
        <label>Identity Status <span style="font-size: 11px; color: var(--text-muted);">(Locked)</span></label>
        <input type="text" value="Alumni Verified Member" readonly style="background: #14141e; color: #a0a0b0; cursor: not-allowed; border: 1px solid rgba(255,255,255,0.05);">
    </div>

    <div class="form-group">
        <label>Current Job Title</label>
        <input type="text" name="job_title" value="<?php echo htmlspecialchars(
            $job_title,
        ); ?>" placeholder="e.g. Software Engineer">
    </div>
    <div class="form-group">
        <label>Current Company</label>
        <input type="text" name="company" value="<?php echo htmlspecialchars(
            $company,
        ); ?>" placeholder="e.g. Intel Corporation">
    </div>
<?php endif; ?>

    <div class="form-group">
        <label>Bio</label>
        <textarea name="bio" rows="4" placeholder="Brief statement about your goals..."><?php echo htmlspecialchars(
            $bio,
        ); ?></textarea>
    </div>
    <button type="submit" name="save_basics" class="btn btn-primary">Save Changes</button>
</form>
        </div>
    </div>

    <div id="projectModal" class="modal-wrapper">
        <div class="modal-box">
            <div class="modal-box-header">
                <h3>Add New Project</h3>
                <button class="modal-close-trigger" onclick="handleSystemClose('projectModal')">&times;</button>
            </div>
            <form action="process_profile.php" method="POST" class="profile-input-form">
                <div class="form-group">
                    <label>Project Name *</label>
                    <input type="text" name="project_name" required>
                </div>
                <div class="form-group">
                    <label>Repository Link <span class="optional-badge">Optional</span></label>
                    <input type="url" name="project_link" placeholder="https://github.com/yourpath">
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="project_desc" rows="4" required placeholder="Highlight core tools used (e.g. Node.js, Firebase)"></textarea>
                </div>
                <button type="submit" name="save_project" class="btn btn-primary">Save Project</button>
            </form>
        </div>
    </div>

    <div id="skillsModal" class="modal-wrapper">
        <div class="modal-box">
            <div class="modal-box-header">
                <h3>Add Core Skill</h3>
                <button class="modal-close-trigger" onclick="handleSystemClose('skillsModal')">&times;</button>
            </div>
            <form action="process_profile.php" method="POST" class="profile-input-form">
                <div class="form-group">
                    <label>Skill Name *</label>
                    <input type="text" name="skill_name" placeholder="e.g. Java, Agile Development" required>
                </div>
                <div class="form-group">
                    <label>Place/Platform <span class="optional-badge">Optional</span></label>
                    <input type="text" name="skill_place" placeholder="e.g. UTeM Coursework, Coursera">
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="skill_desc" rows="3" required placeholder="Describe proficiency or relevant projects built..."></textarea>
                </div>
                <button type="submit" name="save_skill" class="btn btn-primary">Save Skill Set</button>
            </form>
        </div>
    </div>

    <div id="experienceModal" class="modal-wrapper">
        <div class="modal-box">
            <div class="modal-box-header">
                <h3>Add Work / Leadership Experience</h3>
                <button class="modal-close-trigger" onclick="handleSystemClose('experienceModal')">&times;</button>
            </div>
            <form action="process_profile.php" method="POST" class="profile-input-form">
                <div class="form-group">
                    <label>Experience Position Title *</label>
                    <input type="text" name="exp_title" placeholder="e.g. Project Team Leader" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="month" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>End Date <span class="optional-badge">Optional</span></label>
                        <input type="month" name="end_date">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="exp_desc" rows="4" required placeholder="Outline core contributions or tasks accomplished"></textarea>
                </div>
                <button type="submit" name="save_experience" class="btn btn-primary">Save Experience</button>
            </form>
        </div>
    </div>

    <div id="safetyGuardModal" class="modal-wrapper">
        <div class="modal-box confirm-mini-box">
            <h3 style="font-family: var(--font-display); margin-bottom: 8px;">Unsaved Changes</h3>
            <p style="font-size: 13px; color: var(--text-muted);">You have made modifications. Exit anyway without saving?</p>
            <div class="confirm-btn-group">
                <button type="button" class="btn btn-secondary" style="margin:0;" onclick="confirmSystemExit()">Exit</button>
                <button type="button" class="btn btn-primary" onclick="closeModal('safetyGuardModal')">Keep Editing</button>
            </div>
        </div>
    </div>

    <script>
        let isDirty = false;
        let pendingModalCloseId = null;

        // Tracks modification changes accurately across modal fields
        document.querySelectorAll('.profile-input-form input, .profile-input-form textarea, .profile-input-form select').forEach(input => {
            input.addEventListener('input', () => {
                isDirty = true;
            });
            input.addEventListener('change', () => {
                isDirty = true;
            });
        });

        // Suppress dirty tracker logic checks upon valid form saves
        document.querySelectorAll('.profile-input-form').forEach(form => {
            form.addEventListener('submit', () => {
                isDirty = false;
            });
        });

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('modal-active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('modal-active');
        }

        function handleSystemClose(modalId) {
            if (isDirty) {
                pendingModalCloseId = modalId;
                openModal('safetyGuardModal');
            } else {
                closeModal(modalId);
            }
        }

        function confirmSystemExit() {
            isDirty = false;
            closeModal('safetyGuardModal');
            if (pendingModalCloseId) {
                const targetForm = document.getElementById(pendingModalCloseId).querySelector('form');
                if (targetForm) {
                    targetForm.reset(); 
                }
                closeModal(pendingModalCloseId);
                pendingModalCloseId = null;
            }
        }
    </script>
>>>>>>> Stashed changes
</body>
</html>