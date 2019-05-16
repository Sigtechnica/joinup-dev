@api
Feature: Unsubscribe from groups
  In order to reduce spam messages
  As a user of the website
  I need to be able to easily unsubscribe from groups.

  Scenario: Unsubscribe from all groups
    Given user:
      | Username | Eric Cartman             |
      | E-mail   | eric.cartman@example.com |
    Given collections:
      | title                | state     | abstract     |
      | Southpark elementary | validated | Blah blah... |
      | Kenny's house        | draft     | Blah blah... |
      | Koon's hideout       | proposed  | Blah blah... |
    And the following collection user memberships:
      | collection           | user         | roles       |
      | Southpark elementary | Eric Cartman | member      |
      | Kenny's house        | Eric Cartman | owner       |
      | Koon's hideout       | Eric Cartman | facilitator |

    Given I am logged in as "Eric Cartman"
    When I click "My subscriptions"
    # The button is actually a link but is styled as a button.
    Then I should see the link "Unsubscribe from all"
    And the option with text "All notifications" from select "Discussion" is selected in the "Southpark elementary" card
    And the option with text "All notifications" from select "Document" is selected in the "Southpark elementary" card
    And the option with text "All notifications" from select "Event" is selected in the "Southpark elementary" card
    And the option with text "All notifications" from select "News" is selected in the "Southpark elementary" card
    And the option with text "All notifications" from select "Discussion" is selected in the "Kenny's house" card
    And the option with text "All notifications" from select "Document" is selected in the "Kenny's house" card
    And the option with text "All notifications" from select "Event" is selected in the "Kenny's house" card
    And the option with text "All notifications" from select "News" is selected in the "Kenny's house" card
    And the option with text "All notifications" from select "Discussion" is selected in the "Koon's hideout" card
    And the option with text "All notifications" from select "Document" is selected in the "Koon's hideout" card
    And the option with text "All notifications" from select "Event" is selected in the "Koon's hideout" card
    And the option with text "All notifications" from select "News" is selected in the "Koon's hideout" card

    When I click "Unsubscribe from all"
    Then I should see the heading "Unsubscribe from all groups"
    And I should see the following lines of text:
      | Are you sure you want to unsubscribe from all groups? |
      | [collection] Southpark elementary                     |
      | [collection] Kenny's house                            |
      | [collection] Koon's hideout                           |

    When I press "Confirm"
    And I wait for the batch process to finish

    # Checks partially the success messages so it can work like the step that asserts 'lines of text' above.
    Then I should see the following success messages:
      | Success messages                                                    |
      | You will not receive notification for the following 3 items groups. |
      | [collection] Southpark elementary                                   |
      | [collection] Kenny's house                                          |
      | [collection] Koon's hideout                                         |

    And I should be on "/user/subscriptions"
    And I should see the link "Unsubscribe from all"
    And the option with text "No notifications" from select "Discussion" is selected in the "Southpark elementary" card
    And the option with text "No notifications" from select "Document" is selected in the "Southpark elementary" card
    And the option with text "No notifications" from select "Event" is selected in the "Southpark elementary" card
    And the option with text "No notifications" from select "News" is selected in the "Southpark elementary" card
    And the option with text "No notifications" from select "Discussion" is selected in the "Kenny's house" card
    And the option with text "No notifications" from select "Document" is selected in the "Kenny's house" card
    And the option with text "No notifications" from select "Event" is selected in the "Kenny's house" card
    And the option with text "No notifications" from select "News" is selected in the "Kenny's house" card
    And the option with text "No notifications" from select "Discussion" is selected in the "Koon's hideout" card
    And the option with text "No notifications" from select "Document" is selected in the "Koon's hideout" card
    And the option with text "No notifications" from select "Event" is selected in the "Koon's hideout" card
    And the option with text "No notifications" from select "News" is selected in the "Koon's hideout" card

    When I click "Unsubscribe from all"
    Then I should see the text " You do not have any groups to unsubscribe from."
    And I should not see the following lines of text:
      | Are you sure you want to unsubscribe from all groups? |
      | [collection] Southpark elementary                     |
      | [collection] Kenny's house                            |
      | [collection] Koon's hideout                           |