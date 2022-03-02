@tool @tool_selfsignuphardlifecycle
Feature: The hard life cycle for self-signup users tool allows admins to get rid of users who have signed up themselves to Moodle based on a static schedule
  In order to get rid of self-signup users
  As an admin
  I need to configure an unattended life cycle

  Background:
    Given the following config values are set as admin:
      | coveredauth | email | tool_selfsignuphardlifecycle |
      | userdeletionperiod | 200 | tool_selfsignuphardlifecycle |

  Scenario: Manual authenticated users remain untouched by the tool
    Given the following "users" exist:
      | username | firstname | lastname | email             | auth   | suspended | timecreated        |
      | user1    | User      | 1        | user1@example.com | manual | 0         | ## 201 days ago ## |
    And I log in as "admin"
    And I navigate to "Users > Hard life cycle for self-signup users > User list" in site administration
    Then I should not see "user1" in the "#region-main" "css_element"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "User 1" in the "#users" "css_element"
    And ".usersuspended" "css_element" should not exist in the "User 1" "table_row"
    And I run the scheduled task "tool_selfsignuphardlifecycle\task\process_lifecycle"
    And I reload the page
    Then I should see "User 1" in the "#users" "css_element"
    And ".usersuspended" "css_element" should not exist in the "User 1" "table_row"

  Scenario: If user suspension is not enabled, self-signup users are deleted after the user deletion period
    Given the following "users" exist:
      | username | firstname | lastname | email             | auth  | suspended | timecreated        |
      | user1    | User      | 1        | user1@example.com | email | 0         | ## 199 days ago ## |
      | user2    | User      | 2        | user2@example.com | email | 0         | ## 200 days ago ## |
      | user3    | User      | 3        | user3@example.com | email | 0         | ## 201 days ago ## |
    And I log in as "admin"
    And I navigate to "Users > Hard life cycle for self-signup users > User list" in site administration
    Then I should see "Active" in the "user1" "table_row"
    And I should see "Will be deleted" in the "user1" "table_row"
    And I should see "Active" in the "user2" "table_row"
    And I should see "Will be deleted" in the "user2" "table_row"
    And I should see "Active" in the "user3" "table_row"
    And I should see "Will be deleted" in the "user3" "table_row"
    And I run the scheduled task "tool_selfsignuphardlifecycle\task\process_lifecycle"
    And I reload the page
    Then I should see "Active" in the "user1" "table_row"
    And I should see "Will be deleted" in the "user1" "table_row"
    And I should see "Active" in the "user2" "table_row"
    And I should see "Will be deleted" in the "user2" "table_row"
    And I should not see "user3" in the "#region-main" "css_element"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "User 1" in the "#users" "css_element"
    And I should see "User 2" in the "#users" "css_element"
    And I should not see "User 3" in the "#users" "css_element"

  Scenario: If user suspension is enabled, self-signup users are suspended after the user suspension period and then deleted after the user deletion period
    Given the following config values are set as admin:
      | enablesuspension     | 1   | tool_selfsignuphardlifecycle |
      | usersuspensionperiod | 100 | tool_selfsignuphardlifecycle |
    And the following "users" exist:
      | username | firstname | lastname | email             | auth  | suspended | timecreated        |
      | user1    | User      | 1        | user1@example.com | email | 0         | ## 99 days ago ## |
      | user2    | User      | 2        | user2@example.com | email | 0         | ## 100 days ago ## |
      | user3    | User      | 3        | user3@example.com | email | 0         | ## 101 days ago ## |
      | user4    | User      | 4        | user4@example.com | email | 0         | ## 199 days ago ## |
      | user5    | User      | 5        | user5@example.com | email | 0         | ## 200 days ago ## |
      | user6    | User      | 6        | user6@example.com | email | 0         | ## 201 days ago ## |
    And I log in as "admin"
    And I navigate to "Users > Hard life cycle for self-signup users > User list" in site administration
    Then I should see "Active" in the "user1" "table_row"
    And I should see "Will be suspended" in the "user1" "table_row"
    And I should see "Active" in the "user2" "table_row"
    And I should see "Will be suspended" in the "user2" "table_row"
    And I should see "Active" in the "user3" "table_row"
    And I should see "Will be suspended" in the "user3" "table_row"
    And I should see "Active" in the "user4" "table_row"
    And I should see "Will be suspended" in the "user4" "table_row"
    And I should see "Active" in the "user5" "table_row"
    And I should see "Will be suspended" in the "user5" "table_row"
    And I should see "Active" in the "user6" "table_row"
    And I should see "Will be suspended" in the "user6" "table_row"
    And I run the scheduled task "tool_selfsignuphardlifecycle\task\process_lifecycle"
    And I reload the page
    Then I should see "Active" in the "user1" "table_row"
    And I should see "Will be suspended" in the "user1" "table_row"
    And I should see "Active" in the "user2" "table_row"
    And I should see "Will be suspended" in the "user2" "table_row"
    And I should see "Suspended" in the "user3" "table_row"
    And I should see "Will be deleted" in the "user3" "table_row"
    And I should see "Suspended" in the "user4" "table_row"
    And I should see "Will be deleted" in the "user4" "table_row"
    And I should see "Suspended" in the "user5" "table_row"
    And I should see "Will be deleted" in the "user5" "table_row"
    And I should see "Suspended" in the "user6" "table_row"
    And I should see "Will be deleted" in the "user6" "table_row"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "User 1" in the "#users" "css_element"
    And ".usersuspended" "css_element" should not exist in the "User 1" "table_row"
    And I should see "User 2" in the "#users" "css_element"
    And ".usersuspended" "css_element" should not exist in the "User 2" "table_row"
    And I should see "User 3" in the "#users" "css_element"
    And ".usersuspended" "css_element" should exist in the "User 3" "table_row"
    And I should see "User 4" in the "#users" "css_element"
    And ".usersuspended" "css_element" should exist in the "User 4" "table_row"
    And I should see "User 5" in the "#users" "css_element"
    And ".usersuspended" "css_element" should exist in the "User 5" "table_row"
    And I should see "User 6" in the "#users" "css_element"
    And ".usersuspended" "css_element" should exist in the "User 6" "table_row"
    And I run the scheduled task "tool_selfsignuphardlifecycle\task\process_lifecycle"
    And I navigate to "Users > Hard life cycle for self-signup users > User list" in site administration
    Then I should see "Active" in the "user1" "table_row"
    And I should see "Will be suspended" in the "user1" "table_row"
    And I should see "Active" in the "user2" "table_row"
    And I should see "Will be suspended" in the "user2" "table_row"
    And I should see "Suspended" in the "user3" "table_row"
    And I should see "Will be deleted" in the "user3" "table_row"
    And I should see "Suspended" in the "user4" "table_row"
    And I should see "Will be deleted" in the "user4" "table_row"
    And I should see "Suspended" in the "user5" "table_row"
    And I should see "Will be deleted" in the "user5" "table_row"
    And I should not see "user6" in the "#region-main" "css_element"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    Then I should see "User 1" in the "#users" "css_element"
    And ".usersuspended" "css_element" should not exist in the "User 1" "table_row"
    And I should see "User 2" in the "#users" "css_element"
    And ".usersuspended" "css_element" should not exist in the "User 2" "table_row"
    And I should see "User 3" in the "#users" "css_element"
    And ".usersuspended" "css_element" should exist in the "User 3" "table_row"
    And I should see "User 4" in the "#users" "css_element"
    And ".usersuspended" "css_element" should exist in the "User 4" "table_row"
    And I should see "User 5" in the "#users" "css_element"
    And ".usersuspended" "css_element" should exist in the "User 5" "table_row"
    And I should not see "User 6" in the "#users" "css_element"
