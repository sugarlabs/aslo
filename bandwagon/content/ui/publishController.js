/* ***** BEGIN LICENSE BLOCK *****
 *   Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is bandwagon.
 *
 * The Initial Developer of the Original Code is
 * Mozilla Corporation.
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s): David McNamara
 *                 Brian King <brian (at) briks (dot) si>
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */

Bandwagon.Controller.Publish = new function()
{
    this.stringBundle = null;
    this.hasSubmitted = false;
    this.hasSubmittedWithSuccess = false;

    this.publishType = -1; // one of the TYPE_ constants
    this.publishDestination = null; // email address or collection obj
    this.publishExtension = null; // the add-on / theme obj to publish
}

Bandwagon.Controller.Publish.TYPE_COLLECTION = 1;
Bandwagon.Controller.Publish.TYPE_EXISTING_EMAIL = 2;
Bandwagon.Controller.Publish.TYPE_NEW_EMAIL = 3;

Bandwagon.Controller.Publish.init = function()
{
    Bandwagon.Logger.debug("Initializing Bandwagon.Controller.Publish");

    Bandwagon.Controller.Publish.stringBundle = document.getElementById("bandwagon-strings");

    // Handle window arguments

    var inArgs = window.arguments[0];

    Bandwagon.Controller.Publish.publishType = inArgs.publishType;
    Bandwagon.Controller.Publish.publishDestination = inArgs.publishDestination;
    Bandwagon.Controller.Publish.publishExtension = inArgs.publishExtension;

    //document.getElementById("dialog-desc").value = Bandwagon.Controller.Publish.stringBundle.getFormattedString("you.are.sharing.the.add.on", [Bandwagon.Controller.Publish.publishExtension.name]);
    document.getElementById("type-deck").selectedIndex = Bandwagon.Controller.Publish.publishType-1;

    // l10n fallback (bug 493654)
    try 
    {
        document.getElementById("personal-publish-note-label").textContent = Bandwagon.Controller.Publish.stringBundle.getString("enter.a.personal.publish.note.label");
    }
    catch (e)
    {
        document.getElementById("personal-publish-note-label").textContent = document.getElementById("personal-email-note-label").textContent;
    }

    switch (Bandwagon.Controller.Publish.publishType)
    {
        case Bandwagon.Controller.Publish.TYPE_COLLECTION:
            document.getElementById("publishing-to").value = Bandwagon.Controller.Publish.stringBundle.getFormattedString("publishing.to", [Bandwagon.Controller.Publish.publishExtension.name, (Bandwagon.Controller.Publish.publishDestination.name?Bandwagon.Controller.Publish.publishDestination.name:Bandwagon.Controller.Publish.publishDestination.url)]);
            document.getElementById("new-email-box").collapsed = true;
            document.getElementById("sharing-with-box").collapsed = true;
            document.getElementById("publishing-to-box").collapsed = false;
            document.getElementById("sharing-with-new").collapsed = true;
            document.getElementById("personal-email-note-label").collapsed = true;
            document.getElementById("personal-publish-note-label").collapsed = false;
            break;
        case Bandwagon.Controller.Publish.TYPE_EXISTING_EMAIL:
            document.getElementById("sharing-with").textContent = Bandwagon.Controller.Publish.stringBundle.getFormattedString("sharing.with", [Bandwagon.Controller.Publish.publishDestination, Bandwagon.Controller.Publish.publishExtension.name]);
            document.getElementById("new-email-box").collapsed = true;
            document.getElementById("sharing-with-box").collapsed = false;
            document.getElementById("publishing-to-box").collapsed = true;
            document.getElementById("sharing-with-new").collapsed = true;
            document.getElementById("bandwagon-publish").getButton("accept").label = Bandwagon.Controller.Publish.stringBundle.getString("send.email");
            break;
        case Bandwagon.Controller.Publish.TYPE_NEW_EMAIL:
            document.getElementById("sharing-with-new").textContent = Bandwagon.Controller.Publish.stringBundle.getFormattedString("sharing.with.new", [Bandwagon.Controller.Publish.publishExtension.name]);
            document.getElementById("sharing-with-new").collapsed = false;
            document.getElementById("new-email-box").collapsed = false;
            document.getElementById("sharing-with-box").collapsed = true;
            document.getElementById("publishing-to-box").collapsed = true;
            document.getElementById("bandwagon-publish").getButton("accept").label = Bandwagon.Controller.Publish.stringBundle.getString("send.email");
            break;
    }

    Bandwagon.Controller.Publish.invalidate();
}

Bandwagon.Controller.Publish.doAccept = function()
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.Publish.doAccept();");

    if (Bandwagon.Controller.Publish.hasSubmittedWithSuccess)
    {
        document.getElementById("bandwagon-publish").cancelDialog();
        return true;
    }

    if (Bandwagon.Controller.Publish.publishType == Bandwagon.Controller.Publish.TYPE_NEW_EMAIL && document.getElementById("email-address").value == "")
    {
        Bandwagon.Controller.Publish._showError(Bandwagon.Controller.Publish.stringBundle.getString("please.enter.an.email.address"));
        return false;
    }

    Bandwagon.Controller.Publish.hasSubmitted = true;
    Bandwagon.Controller.Publish.invalidate();

    switch (Bandwagon.Controller.Publish.publishType)
    {
        case Bandwagon.Controller.Publish.TYPE_COLLECTION:
            Bandwagon.Controller.Publish._publishToCollection();
            break;
        case Bandwagon.Controller.Publish.TYPE_EXISTING_EMAIL:
            Bandwagon.Controller.Publish._shareToExistingEmail();
            break;
        case Bandwagon.Controller.Publish.TYPE_NEW_EMAIL:
            Bandwagon.Controller.Publish._shareToNewEmail();
            break;
    }

    return false;
}

Bandwagon.Controller.Publish.doCancel = function()
{
    return true;
}

Bandwagon.Controller.Publish._publishToCollection = function()
{
    bandwagonService.publishToCollection(
        Bandwagon.Controller.Publish.publishExtension,
        Bandwagon.Controller.Publish.publishDestination,
        document.getElementById("personal-note").value,
        Bandwagon.Controller.Publish.finished
        );
}

Bandwagon.Controller.Publish._shareToExistingEmail = function()
{
    bandwagonService.shareToEmail(
        Bandwagon.Controller.Publish.publishExtension,
        Bandwagon.Controller.Publish.publishDestination,
        document.getElementById("personal-note").value,
        Bandwagon.Controller.Publish.finished
        );
}

Bandwagon.Controller.Publish._shareToNewEmail = function()
{
    bandwagonService.shareToEmail(
        Bandwagon.Controller.Publish.publishExtension,
        document.getElementById("email-address").value,
        document.getElementById("personal-note").value,
        Bandwagon.Controller.Publish.finished
        );
}

Bandwagon.Controller.Publish.invalidate = function()
{
    document.getElementById("bandwagon-publish").getButton("accept").disabled = Bandwagon.Controller.Publish.hasSubmitted;
    document.getElementById("email-address").disabled = Bandwagon.Controller.Publish.hasSubmitted;
    document.getElementById("personal-note").disabled = Bandwagon.Controller.Publish.hasSubmitted;
    document.getElementById("spinner").collapsed = !Bandwagon.Controller.Publish.hasSubmitted;
    document.getElementById("error").style.visibility = "hidden";
}

Bandwagon.Controller.Publish.finished = function(event)
{
    if (event.isError())
    {
        Bandwagon.Controller.Publish.hasSubmitted = false;
        Bandwagon.Controller.Publish.invalidate();

        if (event.getError().getMessage() == "unknown_addon_guid")
        {
            Bandwagon.Controller.Publish._showError(Bandwagon.Controller.Publish.stringBundle.getString("error.unknown_addon_guid"));
        }
        else if (event.getError().getMessage() == "invalid_parameters")
        {
            // L10n fallback (bug 493656)
            try
            {
                Bandwagon.Controller.Publish._showError(Bandwagon.Controller.Publish.stringBundle.getString("error.invalid_parameters"));
            }
            catch (e)
            {
                Bandwagon.Controller.Publish._showError(event.getError());
            }
        }
        else
        {
            Bandwagon.Controller.Publish._showError(event.getError());
        }

        return;
    }

    Bandwagon.Controller.Publish.hasSubmittedWithSuccess = true;

    if (Bandwagon.Controller.Publish.publishType == Bandwagon.Controller.Publish.TYPE_NEW_EMAIL
        && document.getElementById("remember-email").checked)
    {
        bandwagonService.addPreviouslySharedEmailAddresses(document.getElementById("email-address").value);
    }

    if (Bandwagon.Controller.Publish.publishType == Bandwagon.Controller.Publish.TYPE_COLLECTION)
    {
        if (Bandwagon.Controller.Publish.publishDestination.subscribed)
          bandwagonService.forceCheckForUpdates(Bandwagon.Controller.Publish.publishDestination);

        document.getElementById("error").textContent = Bandwagon.Controller.Publish.stringBundle.getString("the.addon.has.been.published");
    }
    else
    {
        document.getElementById("error").textContent = Bandwagon.Controller.Publish.stringBundle.getString("the.email.has.been.sent");
    }

    document.getElementById("error").style.visibility = "visible";
    document.getElementById("error").style.color = 'green';
    document.getElementById("spinner").collapsed = true;
    document.getElementById("email-address").disabled = true;
    document.getElementById("personal-note").disabled = true;
    document.getElementById("bandwagon-publish").getButton("cancel").hidden = true;
    document.getElementById("bandwagon-publish").getButton("accept").disabled = false;
    document.getElementById("bandwagon-publish").getButton("accept").label = Bandwagon.Controller.Publish.stringBundle.getFormattedString("closing.in", ["2"]);

    setTimeout(function() { document.getElementById("bandwagon-publish").getButton("accept").label = Bandwagon.Controller.Publish.stringBundle.getFormattedString("closing.in", ["1"]); }, 2000);
    setTimeout(function() { document.getElementById("bandwagon-publish").cancelDialog(); }, 3000);
}

Bandwagon.Controller.Publish._showError = function(message)
{
    document.getElementById("error").style.visibility = "visible";
    document.getElementById("error").textContent = message;
}

window.addEventListener("load", Bandwagon.Controller.Publish.init, true);

