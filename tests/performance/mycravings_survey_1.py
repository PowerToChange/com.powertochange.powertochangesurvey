import string
import time
import random

from java.io import FileOutputStream
from net.grinder.script.Grinder import grinder
from net.grinder.script import Test
from net.grinder.plugin.http import HTTPRequest
from net.grinder.common import GrinderException
from HTTPClient import NVPair

tests = {
    "MyCravingsLoadTest" : Test(1, "MyCravings - Load test"),
}
testId = "MyCravingsLoadTest"

# REVIEW: Ensure URL is available
request = HTTPRequest(url="https://stagehub.p2c.com/node/19")

# Current timestamp to uniquely identify the contacts
baseId = time.time()

class TestRunner:
    def __call__(self):
        tid = grinder.threadNumber

        nameSuffix = random.randint(1, 9999999999)
        firstName = "Grinder"
        lastName = "GrinderLast-%d-%d" % (baseId, nameSuffix)
        phone = "%d" % (random.randint(1111111111, 9999999999))
        email = "%s@%s.net" % (firstName, lastName)

        # Alternate between sending emails and text messages
        if (tid % 2 == 0):
            phone = ""

        # REVIEW: Ensure input parameters align with the form
        parameters = (
            NVPair("submitted[civicrm_1_contact_1_contact_first_name]", firstName),
            NVPair("submitted[civicrm_1_contact_1_contact_last_name]", lastName),
            NVPair("submitted[civicrm_1_contact_1_cg7_custom_57]", "grad"),
            NVPair("submitted[civicrm_1_contact_1_cg7_custom_59]", "TestFaculty"),
            NVPair("submitted[civicrm_1_contact_1_cg7_custom_60]", "TestResidence"),
            NVPair("submitted[civicrm_1_contact_1_cg7_custom_61]", "yes"),
            NVPair("submitted[civicrm_1_contact_1_contact_gender_id]", "1"),
            NVPair("submitted[civicrm_1_contact_1_phone_phone]", phone),
            NVPair("submitted[civicrm_1_contact_1_email_email]", email),
            NVPair("submitted[civicrm_2_contact_1_contact_organization_name]", "University of Waterloo"),
            NVPair("submitted[civicrm_1_activity_1_cg18_custom_149]", "power-corporation"),
            NVPair("submitted[civicrm_1_activity_1_cg22_custom_155]", "magazine-spiritual"),
            NVPair("submitted[civicrm_1_activity_1_cg22_custom_156]", "journey-explore"),
            NVPair("submitted[civicrm_1_activity_1_cg22_custom_157]", "gauge-3"),
            NVPair("submitted[civicrm_1_activity_1_cg22_custom_159]", "Grinder"),
            NVPair("submitted[civicrm_1_activity_1_activity_details]", "Submitted by the Grinder load test"),
            NVPair("details[sid]", ""),
            NVPair("details[page_num]", "1"),
            NVPair("details[page_count]", "1"),
            NVPair("details[finished]", "1"),
            NVPair("form_build_id", "form-z4iUj_N5h3x9OaQ9ZjtdjgCHM5RTY3cc0hNpLlatrp8"),
            NVPair("form_id", "webform_client_form_19"),
        )

        # Submit the survey
        response = request.POST(parameters)

        # Debugging
        #writeToFile(response.getText(), testId)

# Write the response
def writeToFile(text, testId):
    filename = "log/%s-%s-page-%d.html" % (grinder.processName,
                                       testId,
                                       grinder.runNumber)

    # Use Java FileOutputStream since it has better Unicode handling
    os = FileOutputStream(filename)
    os.write(text)
    os.close()
