@api @group-b
Feature:
  As a site builder of the site
  In order to be able to better control the structure of my content
  I need to be able to place paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | validated             |

  @javascript
  Scenario: Paragraph sections are multivalue and sort-able.
    Given I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the "Paragraphs collection" collection
    And I open the plus button menu
    And I click "Add custom page"
    And I fill in "Title" with "Paragraphs page"
    And I press "Save"
    Then I should see the heading "Paragraphs page"

    When I open the header local tasks menu
    And I click "Edit"
    # The first paragraphs item is open by default.
    Then there should be 1 paragraph in the "Custom page body" field
    Then I should see the "Remove" button in the "Custom page body" field for paragraph 1
    Given I press "Remove" in the "Custom page body" field for paragraph 1

    Then the page should contain no paragraphs

    Given I press "Add Simple paragraph" in the "Custom page body" paragraphs field
    Then there should be 1 paragraph in the "Custom page body" field

    Given I press "Add Simple paragraph" in the "Custom page body" paragraphs field
    Then there should be 2 paragraphs in the "Custom page body" field

    When I enter "AAAAAAAAAA" in the "Body" wysiwyg editor in the "Custom page body" field for paragraph 1
    And I enter "BBBBBBBBBB" in the "Body" wysiwyg editor in the "Custom page body" field for paragraph 2

    And I drag the table row at position 2 up
    And I press "Save"
    Then there should be 2 paragraphs in the page
    And I should see the following paragraphs in the given order:
      | BBBBBBBBBB |
      | AAAAAAAAAA |

  Scenario: Moderators can add a map value.
    Given custom_page content:
      | title                     | body        | collection            |
      | Don't Mess with the Zohan | Wanna mess? | Paragraphs collection |

    Given I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the custom_page "Don't Mess with the Zohan" edit screen
    Then I should see the button "Add Simple paragraph"
    Then I should not see the button "Add Map"

    When I fill in "Body" with "I'm half Australian, half Mt. Everest"
    And I press "Save"
    Then I should see the success message "Custom page Don't Mess with the Zohan has been updated."
    And I should see "I'm half Australian, half Mt. Everest"

    Given I am logged in as a moderator
    And I go to the custom_page "Don't Mess with the Zohan" edit screen
    Then I should see the button "Add Simple paragraph"
    Then I should see the button "Add Map"

    When I press "Add Map"
    # As the Webtools Map a webservice, we only test a fake JSON.
    And I fill in "JSON" with "{\"foo\":\"bar\"}"
    And I press "Save"
    Then I should see the success message "Custom page Don't Mess with the Zohan has been updated."
    And I should see "I'm half Australian, half Mt. Everest"
    And the response should contain "{\"foo\":\"bar\"}"
