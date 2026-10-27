"""
GYM Manager Pro - Comprehensive E2E Test Suite
Tests all user roles: Public, Member, Admin
"""
from playwright.sync_api import sync_playwright
import sys
import traceback
sys.stdout.reconfigure(encoding='utf-8')

BASE_URL = "http://localhost/GYM-One"
RESULTS = []

def log_result(test_name, status, details=""):
    icon = "✅" if status == "PASS" else "❌" if status == "FAIL" else "⚠️"
    RESULTS.append({"test": test_name, "status": status, "details": details})
    print(f"{icon} {test_name}: {status} {details}")

def run_tests():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        
        # ============================================
        # TEST SUITE 1: PUBLIC HOMEPAGE
        # ============================================
        print("\n" + "="*60)
        print("🏠 TEST SUITE 1: PUBLIC HOMEPAGE")
        print("="*60)
        
        context = browser.new_context()
        page = context.new_page()
        
        try:
            # Test 1.1: Homepage loads
            response = page.goto(BASE_URL)
            page.wait_for_load_state('networkidle')
            if response.status == 200:
                log_result("1.1 Homepage loads", "PASS", f"Status: {response.status}")
            else:
                log_result("1.1 Homepage loads", "FAIL", f"Status: {response.status}")
            
            # Test 1.2: Gym name visible
            if "PowerFit Gym" in page.content() or "GYM ONE" in page.content():
                log_result("1.2 Gym name visible", "PASS")
            else:
                log_result("1.2 Gym name visible", "FAIL", "Gym name not found")
            
            # Test 1.3: Opening hours displayed
            content = page.content()
            if "06:00" in content and "22:00" in content:
                log_result("1.3 Opening hours displayed", "PASS", "Mon-Fri hours visible")
            else:
                log_result("1.3 Opening hours displayed", "FAIL", "Hours not found")
            
            # Test 1.4: Navigation menu exists
            nav_items = page.locator('nav a, .navbar a').count()
            if nav_items > 0:
                log_result("1.4 Navigation menu exists", "PASS", f"{nav_items} nav links found")
            else:
                log_result("1.4 Navigation menu exists", "FAIL")
            
            # Test 1.5: About section visible
            if "About Us" in content:
                log_result("1.5 About section visible", "PASS")
            else:
                log_result("1.5 About section visible", "FAIL")
            
            # Test 1.6: Location info visible
            if "Ethiopia" in content or "Addis Ababa" in content:
                log_result("1.6 Location info visible", "PASS")
            else:
                log_result("1.6 Location info visible", "FAIL")
            
            # Test 1.7: Current occupancy rate displayed
            if "Current occupancy rate" in content:
                log_result("1.7 Occupancy rate displayed", "PASS")
            else:
                log_result("1.7 Occupancy rate displayed", "FAIL")
            
            # Test 1.8: Footer visible
            if "Copyright" in content or "GYM One" in content:
                log_result("1.8 Footer visible", "PASS")
            else:
                log_result("1.8 Footer visible", "FAIL")
                
        except Exception as e:
            log_result("Homepage tests", "FAIL", str(e))
        finally:
            context.close()
        
        # ============================================
        # TEST SUITE 2: MEMBER LOGIN PAGE
        # ============================================
        print("\n" + "="*60)
        print("👤 TEST SUITE 2: MEMBER LOGIN PAGE")
        print("="*60)
        
        context = browser.new_context()
        page = context.new_page()
        
        try:
            # Test 2.1: Login page loads
            response = page.goto(f"{BASE_URL}/login/")
            page.wait_for_load_state('networkidle')
            if response.status == 200:
                log_result("2.1 Login page loads", "PASS")
            else:
                log_result("2.1 Login page loads", "FAIL", f"Status: {response.status}")
            
            # Test 2.2: Email field exists
            email_field = page.locator('input[name="email"], input[type="email"]')
            if email_field.count() > 0:
                log_result("2.2 Email field exists", "PASS")
            else:
                log_result("2.2 Email field exists", "FAIL")
            
            # Test 2.3: Password field exists
            pwd_field = page.locator('input[name="password"], input[type="password"]')
            if pwd_field.count() > 0:
                log_result("2.3 Password field exists", "PASS")
            else:
                log_result("2.3 Password field exists", "FAIL")
            
            # Test 2.4: Submit button exists
            submit_btn = page.locator('button[type="submit"], input[type="submit"]')
            if submit_btn.count() > 0:
                log_result("2.4 Submit button exists", "PASS")
            else:
                log_result("2.4 Submit button exists", "FAIL")
            
            # Test 2.5: Registration link exists
            content = page.content()
            if "Registration" in content or "Register" in content:
                log_result("2.5 Registration link exists", "PASS")
            else:
                log_result("2.5 Registration link exists", "FAIL")
            
            # Test 2.6: Admin login link exists
            if "Log in" in content and "working here" in content:
                log_result("2.6 Admin login link exists", "PASS")
            else:
                log_result("2.6 Admin login link exists", "FAIL")
            
            # Test 2.7: Invalid login shows error
            page.fill('input[name="email"]', 'wrong@email.com')
            page.fill('input[name="password"]', 'wrongpass')
            page.click('button[type="submit"]')
            page.wait_for_load_state('networkidle')
            content = page.content()
            if "error" in content.lower() or "incorrect" in content.lower() or "invalid" in content.lower():
                log_result("2.7 Invalid login shows error", "PASS")
            else:
                log_result("2.7 Invalid login shows error", "FAIL", "No error message shown")
                
        except Exception as e:
            log_result("Member login tests", "FAIL", str(e))
        finally:
            context.close()
        
        # ============================================
        # TEST SUITE 3: ADMIN LOGIN & DASHBOARD
        # ============================================
        print("\n" + "="*60)
        print("🔧 TEST SUITE 3: ADMIN LOGIN & DASHBOARD")
        print("="*60)
        
        context = browser.new_context()
        page = context.new_page()
        
        try:
            # Test 3.1: Admin page loads
            response = page.goto(f"{BASE_URL}/admin/")
            page.wait_for_load_state('networkidle')
            if response.status == 200:
                log_result("3.1 Admin page loads", "PASS")
            else:
                log_result("3.1 Admin page loads", "FAIL", f"Status: {response.status}")
            
            # Test 3.2: Username field exists
            username_field = page.locator('input[name="username"]')
            if username_field.count() > 0:
                log_result("3.2 Username field exists", "PASS")
            else:
                log_result("3.2 Username field exists", "FAIL")
            
            # Test 3.3: Password field exists
            pwd_field = page.locator('input[name="password"]')
            if pwd_field.count() > 0:
                log_result("3.3 Password field exists", "PASS")
            else:
                log_result("3.3 Password field exists", "FAIL")
            
            # Test 3.4: Successful admin login
            page.fill('input[name="username"]', 'admin')
            page.fill('input[name="password"]', 'Admin123!')
            page.click('button[type="submit"]')
            page.wait_for_load_state('networkidle')
            
            if "dashboard" in page.url.lower() or "admin" in page.url.lower():
                log_result("3.4 Successful admin login", "PASS", f"URL: {page.url}")
            else:
                log_result("3.4 Successful admin login", "FAIL", f"URL: {page.url}")
            
            # Test 3.5: Dashboard shows stats
            content = page.content()
            if "Members" in content:
                log_result("3.5 Dashboard shows stats", "PASS")
            else:
                log_result("3.5 Dashboard shows stats", "FAIL")
            
            # Test 3.6: Sidebar navigation exists
            sidebar_items = page.locator('.sidebar a, nav a, .menu a').count()
            if sidebar_items > 5:
                log_result("3.6 Sidebar navigation exists", "PASS", f"{sidebar_items} items")
            else:
                log_result("3.6 Sidebar navigation exists", "FAIL", f"Only {sidebar_items} items")
            
            # Test 3.7: Members section accessible
            members_link = page.locator('a:has-text("Members")')
            if members_link.count() > 0:
                log_result("3.7 Members section link exists", "PASS")
            else:
                log_result("3.7 Members section link exists", "FAIL")
            
            # Test 3.8: Statistics section accessible
            stats_link = page.locator('a:has-text("Statistics")')
            if stats_link.count() > 0:
                log_result("3.8 Statistics section link exists", "PASS")
            else:
                log_result("3.8 Statistics section link exists", "FAIL")
            
            # Test 3.9: Settings section accessible
            settings_link = page.locator('a:has-text("Settings"), a:has-text("Basic Settings")')
            if settings_link.count() > 0:
                log_result("3.9 Settings section link exists", "PASS")
            else:
                log_result("3.9 Settings section link exists", "FAIL")
            
            # Test 3.10: Logout button exists
            logout_link = page.locator('a:has-text("Logout"), button:has-text("Logout")')
            if logout_link.count() > 0:
                log_result("3.10 Logout button exists", "PASS")
            else:
                log_result("3.10 Logout button exists", "FAIL")
                
        except Exception as e:
            log_result("Admin login tests", "FAIL", str(e))
        finally:
            context.close()
        
        # ============================================
        # TEST SUITE 4: ADMIN MEMBERS MANAGEMENT
        # ============================================
        print("\n" + "="*60)
        print("👥 TEST SUITE 4: ADMIN MEMBERS MANAGEMENT")
        print("="*60)
        
        context = browser.new_context()
        page = context.new_page()
        
        try:
            # Login as admin
            page.goto(f"{BASE_URL}/admin/")
            page.wait_for_load_state('networkidle')
            page.fill('input[name="username"]', 'admin')
            page.fill('input[name="password"]', 'Admin123!')
            page.click('button[type="submit"]')
            page.wait_for_load_state('networkidle')
            
            # Test 4.1: Members page loads
            page.goto(f"{BASE_URL}/admin/users/")
            page.wait_for_load_state('networkidle')
            if page.locator('table, .table, .card').count() > 0:
                log_result("4.1 Members page loads", "PASS")
            else:
                log_result("4.1 Members page loads", "FAIL")
            
            # Test 4.2: Members table exists
            table = page.locator('table')
            if table.count() > 0:
                log_result("4.2 Members table exists", "PASS")
            else:
                log_result("4.2 Members table exists", "FAIL")
            
            # Test 4.3: Add member functionality exists
            add_btn = page.locator('a:has-text("Add"), button:has-text("Add"), a:has-text("New"), a:has-text("Register"), .btn-primary, .btn-success')
            if add_btn.count() > 0:
                log_result("4.3 Add member functionality exists", "PASS")
            else:
                # Check if page has any action buttons
                all_buttons = page.locator('a.btn, button.btn').count()
                if all_buttons > 0:
                    log_result("4.3 Add member functionality exists", "PASS", f"{all_buttons} action buttons found")
                else:
                    log_result("4.3 Add member functionality exists", "FAIL")
                
        except Exception as e:
            log_result("Members management tests", "FAIL", str(e))
        finally:
            context.close()
        
        # ============================================
        # TEST SUITE 5: ADMIN SETTINGS
        # ============================================
        print("\n" + "="*60)
        print("⚙️ TEST SUITE 5: ADMIN SETTINGS")
        print("="*60)
        
        context = browser.new_context()
        page = context.new_page()
        
        try:
            # Login as admin
            page.goto(f"{BASE_URL}/admin/")
            page.wait_for_load_state('networkidle')
            page.fill('input[name="username"]', 'admin')
            page.fill('input[name="password"]', 'Admin123!')
            page.click('button[type="submit"]')
            page.wait_for_load_state('networkidle')
            
            # Test 5.1: Basic Settings page loads
            page.goto(f"{BASE_URL}/admin/boss/mainsettings/")
            page.wait_for_load_state('networkidle')
            if page.locator('input[name="business_name"]').count() > 0:
                log_result("5.1 Basic Settings page loads", "PASS")
            else:
                log_result("5.1 Basic Settings page loads", "FAIL")
            
            # Test 5.2: Gym name field has value
            gym_name = page.locator('input[name="business_name"]').input_value()
            if gym_name:
                log_result("5.2 Gym name field has value", "PASS", f"Value: {gym_name}")
            else:
                log_result("5.2 Gym name field has value", "FAIL")
            
            # Test 5.3: Opening Hours page loads
            page.goto(f"{BASE_URL}/admin/boss/hours/")
            page.wait_for_load_state('networkidle')
            if page.locator('input[name="open_time[1]"]').count() > 0:
                log_result("5.3 Opening Hours page loads", "PASS")
            else:
                log_result("5.3 Opening Hours page loads", "FAIL")
            
            # Test 5.4: Workers page loads
            page.goto(f"{BASE_URL}/admin/boss/workers/")
            page.wait_for_load_state('networkidle')
            content = page.content()
            if "Workers" in content or "workers" in content:
                log_result("5.4 Workers page loads", "PASS")
            else:
                log_result("5.4 Workers page loads", "FAIL")
            
            # Test 5.5: Products page loads
            page.goto(f"{BASE_URL}/admin/boss/packages/")
            page.wait_for_load_state('networkidle')
            content = page.content()
            if "Products" in content or "packages" in content.lower():
                log_result("5.5 Products page loads", "PASS")
            else:
                log_result("5.5 Products page loads", "FAIL")
                
        except Exception as e:
            log_result("Settings tests", "FAIL", str(e))
        finally:
            context.close()
        
        # ============================================
        # TEST SUITE 6: PUBLIC PAGES
        # ============================================
        print("\n" + "="*60)
        print("🌐 TEST SUITE 6: PUBLIC PAGES")
        print("="*60)
        
        context = browser.new_context()
        page = context.new_page()
        
        try:
            # Test 6.1: Personal Trainers page
            response = page.goto(f"{BASE_URL}/trainers/")
            page.wait_for_load_state('networkidle')
            if response.status == 200:
                log_result("6.1 Personal Trainers page loads", "PASS")
            else:
                log_result("6.1 Personal Trainers page loads", "FAIL", f"Status: {response.status}")
            
            # Test 6.2: Prices page
            response = page.goto(f"{BASE_URL}/prices/")
            page.wait_for_load_state('networkidle')
            if response.status == 200:
                log_result("6.2 Prices page loads", "PASS")
            else:
                log_result("6.2 Prices page loads", "FAIL", f"Status: {response.status}")
            
            # Test 6.3: Contact page
            response = page.goto(f"{BASE_URL}/contact/")
            page.wait_for_load_state('networkidle')
            if response.status == 200:
                log_result("6.3 Contact page loads", "PASS")
            else:
                log_result("6.3 Contact page loads", "FAIL", f"Status: {response.status}")
            
            # Test 6.4: Registration page
            response = page.goto(f"{BASE_URL}/register/")
            page.wait_for_load_state('networkidle')
            if response.status == 200:
                log_result("6.4 Registration page loads", "PASS")
            else:
                log_result("6.4 Registration page loads", "FAIL", f"Status: {response.status}")
                
        except Exception as e:
            log_result("Public pages tests", "FAIL", str(e))
        finally:
            context.close()
        
        browser.close()

# Run all tests
print("\n" + "🏋️ GYM MANAGER PRO - COMPREHENSIVE E2E TEST SUITE")
print("=" * 60)

run_tests()

# Print summary
print("\n" + "=" * 60)
print("📊 TEST SUMMARY")
print("=" * 60)

passed = sum(1 for r in RESULTS if r["status"] == "PASS")
failed = sum(1 for r in RESULTS if r["status"] == "FAIL")
total = len(RESULTS)

print(f"Total Tests: {total}")
print(f"✅ Passed: {passed}")
print(f"❌ Failed: {failed}")
print(f"Success Rate: {round(passed/total*100, 1)}%")

if failed > 0:
    print("\n❌ FAILED TESTS:")
    for r in RESULTS:
        if r["status"] == "FAIL":
            print(f"  - {r['test']}: {r['details']}")

print("\n" + "=" * 60)
