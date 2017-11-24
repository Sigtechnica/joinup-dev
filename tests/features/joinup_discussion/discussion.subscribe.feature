@api
Feature: Subscribing to discussions
  As a member of Joinup
  I want to subscribe to interesting discussions
  So that I can stay up to date with its evolvements.

  Background:
    Given the following collection:
      | title | Dairy products |
      | state | validated      |
    And user:
      | Username | Dr. Hans Zarkov  |
      | E-mail   | hans@example.com |
    And discussion content:
      | title       | body                                                             | collection     | state     | author          |
      | Rare Butter | I think that the rarest butter out there is the milky way butter | Dairy products | validated | Dr. Hans Zarkov |
    Then the "Rare butter" discussion should have 0 subscribers

  Scenario: Subscribe to a discussion.
    Given I am an anonymous user
    And I go to the "Rare Butter" discussion
    Then I should not see the link "Subscribe"
    And I should not see the link "Unsubscribe"

    Given I am logged in as an "authenticated user"
    And I go to the "Rare Butter" discussion
    Then I should see the link "Subscribe"
    And I should not see the link "Unsubscribe"

    Given I click "Subscribe"
    Then I should see the link "Unsubscribe"
    And I should not see the link "Subscribe"
    And the "Rare butter" discussion should have 1 subscriber

    Given I click "Unsubscribe"
    Then I should see the heading "Unsubscribe from this discussion?"
    When I press "Unsubscribe"
    Then I should see the heading "Rare Butter"
    And I should see the link "Subscribe"
    And the "Rare butter" discussion should have 0 subscribers

  @email
  Scenario: Receive an E-mail notification when a comment is added.
    Given users:
      | Username | E-mail            | First name | Family name |
      | follower | dale@example.com  | Dale       | Arden       |
      | debater  | flash@example.com | Flash      | Gordon      |
    And I am logged in as follower
    And I go to the "Rare Butter" discussion
    When I click "Subscribe"

    # Anonymous users comments are notifications are sent in comment approval.
    Given I am an anonymous user
    And I go to the "Rare Butter" discussion
    Then I fill in "Create comment" with "Is Dale in love with Flash?"
    And I fill in "Your name" with "Gerhardt von Troll"
    And I fill in "Email" with "trollingismylife@example.com"
    But I wait for the honeypot validation to pass
    Then I press "Post comment"
    # Moderate the anonymous comment.
    Given I am logged in as a user with the "administer comments" permission
    And I go to "/admin/content/comment/approval"
    Given I select the "Rare Butter" row
    Then I select "Publish comment" from "Action"
    And I press "Apply to selected items"
    # Subscribers are receiving the notifications.
    And the following email should have been sent:
      | recipient | dale@example.com                                                                                    |
      | subject   | Joinup: User Gerhardt von Troll posted a comment in discussion "Rare Butter"                        |
      | body      | Gerhardt von Troll has posted a comment on discussion "Rare Butter" in "Dairy products" collection. |
    # Discussion author is receiving the notifications too.
    And the following email should have been sent:
      | recipient | hans@example.com                                                                                    |
      | subject   | Joinup: User Gerhardt von Troll posted a comment in discussion "Rare Butter"                        |
      | body      | Gerhardt von Troll has posted a comment on discussion "Rare Butter" in "Dairy products" collection. |

    # Authenticated users comments are sent on comment creation.
    Given I am logged in as debater
    And I go to the "Rare Butter" discussion
    And I fill in "Create comment" with "I'm the moderator of this discussion. Let's talk."
    But I wait for the honeypot validation to pass
    Then I press "Post comment"
    # Subscribers are receiving the notifications.
    And the following email should have been sent:
      | recipient | dale@example.com                                                                              |
      | subject   | Joinup: User Flash Gordon posted a comment in discussion "Rare Butter"                      |
      | body      | Flash Gordon has posted a comment on discussion "Rare Butter" in "Dairy products" collection. |
    # Discussion author is receiving the notifications too.
    And the following email should have been sent:
      | recipient | hans@example.com                                                                              |
      | subject   | Joinup: User Flash Gordon posted a comment in discussion "Rare Butter"                      |
      | body      | Flash Gordon has posted a comment on discussion "Rare Butter" in "Dairy products" collection. |
