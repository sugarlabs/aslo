#!/usr/bin/python

# ***** BEGIN LICENSE BLOCK *****
# Version: MPL 1.1/GPL 2.0/LGPL 2.1
#
# The contents of this file are subject to the Mozilla Public License Version
# 1.1 (the "License"); you may not use this file except in compliance with
# the License. You may obtain a copy of the License at
# http://www.mozilla.org/MPL/
#
# Software distributed under the License is distributed on an "AS IS" basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
# for the specific language governing rights and limitations under the
# License.
#
# The Original Code is addons.mozilla.org site.
#
# The Initial Developer of the Original Code is
# The Mozilla Foundation.
# Portions created by the Initial Developer are Copyright (C) 2006
# the Initial Developer. All Rights Reserved.
#
# Contributor(s):
#   Lars Lohn <lars@mozilla.com> (Original Author)
#
# Alternatively, the contents of this file may be used under the terms of
# either the GNU General Public License Version 2 or later (the "GPL"), or
# the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
# in which case the provisions of the GPL or the LGPL are applicable instead
# of those above. If you wish to allow use of your version of this file only
# under the terms of either the GPL or the LGPL, and not to allow others to
# use your version of this file under the terms of the MPL, indicate your
# decision by deleting the provisions above and replace them with the notice
# and other provisions required by the GPL or the LGPL. If you do not delete
# the provisions above, a recipient may use your version of this file under
# the terms of any one of the MPL, the GPL or the LGPL.
#
# ***** END LICENSE BLOCK *****

import cse.Database
import cse.MySQLDatabase
import cse.TabularData

from  Crypto.Hash import *

import mx.DateTime
import os.path
import urllib2
import urllib
import Image

import sys
import os
import traceback

version = "0.7"

standardError = sys.stderr
#standardError = sys.stdout
#sys.stderr = sys.stdout

class MigrationException(Exception):
  pass

#-----------------------------------------------------------------------------------------------------------
# imageAndThumbFromURL
#-----------------------------------------------------------------------------------------------------------
def imageAndThumbFromURL (aURL, xThumbSize=200, yThumbSize=150, scratchDirectory=".", transparentPngPathName="./blank.png"):
  urlReader = urllib2.urlopen(aURL)
  contentType = urlReader.info()['Content-Type']
  content = urlReader.read()
  
  temporaryFileName = ("%s/i.%s" % (scratchDirectory, aURL[-3:])).replace("//", "/")
  temporaryThumbFileName = ("%s/t.png" % (scratchDirectory, )).replace("//", "/")
  f = open(temporaryFileName, "w")
  try:
    f.write(content)
    f.close()
    
    im = Image.open(temporaryFileName)
    try:
      im.thumbnail((xThumbSize, yThumbSize), Image.ANTIALIAS)
      targetImage = Image.open(transparentPngPathName)
      targetImage.paste(im, ((xThumbSize - im.size[0]) / 2, (yThumbSize - im.size[1]) / 2))
      targetImage.save(temporaryThumbFileName)
      
      f = open(temporaryThumbFileName)
      thumbContent = f.read()
      f.close()
    finally:
      os.unlink(temporaryThumbFileName)
  finally:
    os.unlink(temporaryFileName)
  return (content, contentType, thumbContent, "image/png")

#-----------------------------------------------------------------------------------------------------------
# sha1Hash
#-----------------------------------------------------------------------------------------------------------
def sha1Hash (aURL, addonID, cachePath):
  basename = os.path.basename(aURL)
  try:
    f = open ("%s/%d/%s" % (cachePath, addonID, basename))
    #print "getting from cache"
    block = f.read()
    f.close()
    fromCache = True
  except:
    #print "getting from web"
    fromCache = False
    aURL = urllib.quote(aURL, "/:")
    urlReader = urllib2.urlopen(aURL)
    block = urlReader.read()
  shaCalculator = SHA.new()
  shaCalculator.update(block)
  if not fromCache and cachePath:
    try:
      f = open("%s/%d/%s" % (cachePath, addonID, basename), "w")
    except:
      #print "missing directory %s/%d" % (cachePath, addonID)
      os.mkdir("%s/%d" % (cachePath, addonID))
      f = open("%s/%d/%s" % (cachePath, addonID, basename), "w")
    #print "putting to cache"
    f.write(block)
    f.close()
    
  return "sha1:%s" % shaCalculator.hexdigest()

#-----------------------------------------------------------------------------------------------------------
# firstWords
#-----------------------------------------------------------------------------------------------------------
def firstWords (originalString, maxCharacters = 250, maxSearchBack = 20):
  """This function returns the first maxCharacters of originalString making sure that the end of the 
     string was cut on a word break and elipsis (...) was appended.
     
     input:
       originalString - the string
       maxCharacters - the maximum number of characters to return
       maxSearchBack - if no word break was found after searching back this many characters, give up and
                       return the maximum number of characters
  """
  try:
    originalString = originalString.lstrip()
    if len(originalString) <= maxCharacters: 
      maxCharacters = len(originalString)
    breakIndex = maxCharacters - 3
    maximumSearchBackIndex = breakIndex - maxSearchBack
    if maximumSearchBackIndex < 0:
      maximumSearchBackIndex = 0
    firstLineBreak = min(originalString.find('\n'), originalString.find('\r'))
    if -1 < firstLineBreak < breakIndex:
      breakIndex = firstLineBreak
    while breakIndex > maximumSearchBackIndex and not (originalString[breakIndex] == " " and originalString[breakIndex-1] != " "):
      breakIndex -= 1
    if breakIndex == maximumSearchBackIndex:
      breakIndex = maxCharacters - 3
    try:
      if originalString[breakIndex - 1] in ".,:\"'!":
        breakIndex -= 1
      returnPhrase = "%s..." % originalString[:breakIndex]
    except IndexError:
      returnPhrase = ""    
    assert(len(returnPhrase) <= maxCharacters)
    return returnPhrase
  except AssertionError:
    print "%d [[[%s]]]" % (len(originalString), originalString)
    print "%d <<<%s>>>" % (len(returnPhrase), returnPhrase)
  except TypeError, x:
    print >>standardError, x
    print >>standardError, "  %s passed in as a string" % originalString
  except Exception, x:
    print >>standardError, x
    traceback.print_exc(file=standardError)

#-----------------------------------------------------------------------------------------------------------
# yesNoEnumToTinyIntMappings
#-----------------------------------------------------------------------------------------------------------
yesNoEnumToTinyIntMappingForApproval = { "YES": 4, "NO": 1, "?":2, "DISABLED":5 }
yesNoEnumToTinyIntMappingForHighlight = { "YES": 1, "NO": 0 }


#-----------------------------------------------------------------------------------------------------------
# nullToEmptyString
#-----------------------------------------------------------------------------------------------------------
def nullToEmptyString (aString):
  if aString is None:
    return ""
  return aString

#-----------------------------------------------------------------------------------------------------------
# nullToEmptyDate
#-----------------------------------------------------------------------------------------------------------
def nullToEmptyDate (aDate):
  if aDate is None:
    return "0000-00-00 00:00:00"
  return aDate

#-----------------------------------------------------------------------------------------------------------
# addTranslation
#-----------------------------------------------------------------------------------------------------------
def addTranslation (newDB, aTransalationString, aLocaleString):
  """adds a new translation to the translations table and returns the index"""
  
  newDB.executeSql("UPDATE translations_seq SET id=LAST_INSERT_ID(id+1)")
  newID = newDB.singleValueSql("SELECT LAST_INSERT_ID()")
  newDB.commit()
  newDB.executeManySql("insert into translations (id, locale, localized_string, created) values (%s, %s, %s, %s)", [(newID, aLocaleString, aTransalationString, mx.DateTime.now())] )
  newDB.commit()
  return newID

#-----------------------------------------------------------------------------------------------------------
# cleanMetaDataTables
#-----------------------------------------------------------------------------------------------------------
def cleanMetaDataTables (newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tclearing metadata tables..." % mx.DateTime.now()
  listOfTranslationsToDelete = newDB.executeSql("""
    select name from applications 
    union 
    select shortname from applications
    union
    select name from platforms
    union
    select shortname from platforms
    union
    select name from tags
    union
    select description from tags""").contents[1]
  newDB.executeSql("delete from tags")
  newDB.executeSql("delete from appversions")
  newDB.executeSql("delete from applications")
  newDB.executeSql("delete from platforms")
  newDB.executeSql("delete from addons_users")
  newDB.executeSql("delete from users")
  newDB.executeManySql("delete from translations where id = %s", listOfTranslationsToDelete)
  newDB.commit()
    
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."


#-----------------------------------------------------------------------------------------------------------
# applicationsToApplications
#-----------------------------------------------------------------------------------------------------------
appversionCorrection = { "Firefox": { "0.9.x":"0.9.3",
                                       "1.0PR":"0.10",
                                       "1.5.0.4":"1.5.0.*",
                                       "1.6a1":"1.5.0.*",
                                       "Deer Park":"1.0+"
                                      },
                          "Thunderbird": { "1.5.0.5": "1.5.0.*",
                                           "1.5beta": "1.5b",
                                           "1.6a1": "1.5.0.*"
                                         }
                        }

appversionCorrectionForMin = { "Firefox": { ".9":"0.9",
                                          "0.1":"0.10",
                                          "0.80":"0.8",
                                          "0.9.6":"0.10",
                                          "0.9.x":"0.10",
                                          "00.8":"0.8",
                                          "1.0PR":"0.10",
                                          "1.4.0":"1.4",
                                          "1.5*":"1.5",
                                          "1.5.0.*":"1.5",
                                          "1.5.0.4":"1.5",
                                          "1.6":"2.0a1",
                                          "2.0b1":"2.0b2",
                                          "Deer Park":"1.5"
                                         },
                             "Flock": { "0.1":"0.1",
                                        "0.4.0":"0.4"
                                      },
                             "Mozilla": { "-": "1.0",
                                          "1":"1.0",
                                          "1.8+":"1.8"
                                        },
                             "Netscape": { "8":"8.0"
                                         },
                             "Nvu": { "1.0":"1.1"
                                    },
                             "SeaMonkey": { "1.0":"1.1"
                                          },
                             "Sunbird": { "0.3.1":"0.3a1"
                                        },
                             "Thunderbird": { "1.4":"1.5",
                                              "1.4.1":"1.5",
                                              "1.5.0.*":"1.5",
                                              "1.5.0.2":"1.5",
                                              "1.5.0.4":"1.5",
                                              "1.5.0.5":"1.5",
                                              "1.6":"2.0"
                                            }
                           }
appversionCorrectionForMax = { "Firefox": { "0.10.1":"0.10",
                                          "0.10.1+":"0.10.1",
                                          "0.11":"0.10",
                                          "01.6":"1.5.0.*",
                                          "1.1":"1.0.8",
                                          "1.5":"1.5.0.*",
                                          "1.5.0.*":"1.5.0.*",
                                          "1.5.0.4":"1.5.0.*",
                                          "1.6":"1.5.0.*",
                                          "1.6a1":"1.5.0.*",
                                          "2.0":"2.0.0.*",
                                          "2.0.0.*":"2.0.0.*",
                                          "2.0.0.a3":"2.0a3",
                                          "2.0.0.b1":"2.0b1",
                                          "3.0":"3.0a1",
                                          "3.0.0.a1":"3.0a1",
                                          "Deer Park":"1.0+"
                                        },
                             "Flock": { "0.*":"0.5.*",
                                        "0.8.x":"0.8.*",
                                        "10.0":"1.0",
                                        "6.02":"6.0",
                                        "9999.0+":"1.0+"
                                      },
                             "Mozilla": { "-":"1.0",
                                          "1":"1.0",
                                          "1.5.0.*":"1.5",
                                        },
                             "SeaMonkey": { "1.5.0.a":"1.5a" 
                                          },
                             "Sunbird": { "0.3.1":"0.3a1" 
                                        },
                             "Thunderbird": { "0.10":"0.9",
                                              "0.9.0+":"0.9+",
                                              "1.2":"1.1a1",
                                              "1.4":"1.5b1",
                                              "1.4.1":"1.5b2",
                                              "1.5":"1.5.0.*",
                                              "1.5.0.2":"1.5.0.*",
                                              "1.5.0.4":"1.5.0.*",
                                              "1.6":"1.5.0.*",
                                              "1.6a1":"1.5.0.*",
                                              "2.0":"2.0.0.*",
                                              "3.0":"3.0a1",
                                              "3.0a1":"3.0a1"
                                            }
                           }
#-----------------------------------------------------------------------------------------------------------
# correctAppversion
#-----------------------------------------------------------------------------------------------------------
def correctAppversion (appName, versionName, correctionDictionary):
  try:
    return correctionDictionary[appName][versionName]
  except KeyError:
    return versionName
    
#-----------------------------------------------------------------------------------------------------------
# applicationsToApplications
#-----------------------------------------------------------------------------------------------------------
applicationsInsertSql = """
  insert into applications (id, guid, name, shortname, supported, created)
  values (%s, %s, %s, %s, %s, %s)"""
appversionsInsertSql = """
  insert into appversions (id, application_id, version, created)
  values (%s, %s, %s, %s)"""
def applicationsToApplications (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning applicationsToApplications..." % mx.DateTime.now()
  applicationsAlreadyInserted = {}
  appversionsAlreadyInserted = {}
  for a in oldDB.executeSqlNoCache("select * from applications"):
    if a.AppName not in applicationsAlreadyInserted:
      newDB.executeManySql(applicationsInsertSql, [ 
              (a.AppID, #id
               a.GUID,  #guid
               addTranslation(newDB, a.AppName, "en-US"), #name
               addTranslation(newDB, a.AppName, "en-US"), #shortname
               a.supported, #supported
               mx.DateTime.now()) #created
            ] )
      applicationsAlreadyInserted[a.AppName] = a.AppID
      appversionsAlreadyInserted[a.AppName] = {}
    correctedAppversion = correctAppversion(a.AppName, a.Version, appversionCorrection)
    if correctedAppversion not in appversionsAlreadyInserted[a.AppName]:
      newDB.executeManySql(appversionsInsertSql, [ 
        (a.AppID, #id
         applicationsAlreadyInserted[a.AppName], #application_id
         correctedAppversion, #version
         mx.DateTime.now()) #created
      ] )
      appversionsAlreadyInserted[a.AppName][correctedAppversion] = True
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."


#-----------------------------------------------------------------------------------------------------------
# categoriesToTags
#-----------------------------------------------------------------------------------------------------------
tagsInsertSql = """
  insert into tags (id, name, description, addontype_id, application_id, created)
  values (%s, %s, %s, %s, %s, %s)"""
typeEnumToTypeNameMapping = {'E': 'Extension',
                             'T': 'Theme',
                             'P': 'Plugin' }
def categoriesToTags (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning categoriesToTags..." % mx.DateTime.now()
  for c in oldDB.executeSqlNoCache("select * from categories"):
    newDB.executeManySql(tagsInsertSql, [ (c.CategoryID, #id
                                           addTranslation(newDB, c.CatName, workingEnvironment["locale"]), #name
                                           addTranslation(newDB, c.CatDesc, workingEnvironment["locale"]), #description
                                           newDB.singleValueSql("select a.id from addontypes a join translations t on a.name = t.id and t.locale = 'en-US' and localized_string = '%s'" 
                                                                 % typeEnumToTypeNameMapping[c.CatType]), #addontype_id
                                           newDB.singleValueSql("select a.id from applications a join translations t on a.name = t.id and t.locale = 'en-US' and localized_string = '%s'" % c.CatApp), #application_id
                                           mx.DateTime.now()) #created
                                         ] )
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."


#-----------------------------------------------------------------------------------------------------------
# osToPlatforms
#-----------------------------------------------------------------------------------------------------------
plaformsInsertSql = """
  insert into platforms (id, name, shortname, created)
  values (%s, %s, %s, %s)"""
def osToPlatforms (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning osToPlatforms..." % mx.DateTime.now()
  for o in oldDB.executeSqlNoCache("select * from os"):
    newDB.executeManySql(plaformsInsertSql, [ 
      (o.OSID, #id
       addTranslation(newDB, o.OSName, "en-US"), #name
       addTranslation(newDB, o.OSName, "en-US"), #shortname
       mx.DateTime.now()) #created
    ] )
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."


#-----------------------------------------------------------------------------------------------------------
# userprofilesToUsers
#-----------------------------------------------------------------------------------------------------------
usersInsertSql = """
  insert into users (id, firstname, lastname, nickname, email, homepage, password, emailhidden, confirmationcode, created, notes)
  values (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""
def userprofilesToUsers (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning userprofilesToUsers..." % mx.DateTime.now()
  for u in oldDB.executeSqlNoCache("select * from userprofiles"):
    splitName = u.UserName.split()
    try:
      lastName = splitName[-1]
    except:
      lastName = "?"
    try:
      firstName = ' '.join(splitName[:-1])
    except:
      firstname = "?"
    newDB.executeManySql(usersInsertSql, [ 
      (u.UserID, #id
       firstName, #firstname
       lastName, #lastname
       '', #nickname
       u.UserEmail, #email
       u.UserWebsite, #homepage
       u.UserPass, #password
       u.UserEmailHide, #emailhidden
       u.ConfirmationCode, #confirmationcode
       mx.DateTime.now(), #created
       None) #notes
    ] )
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."


#-----------------------------------------------------------------------------------------------------------
# cleanAddonsTables
#-----------------------------------------------------------------------------------------------------------
def cleanAddonsTables (newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tclearing addons tables..." % mx.DateTime.now()
  if "all" in workingEnvironment["addons"]:
    listOfTranslationsToDelete = newDB.executeSql("""
      select caption from previews
      union
      select releasenotes from versions
      union
      select name from addons
      union 
      select homepage from addons
      union
      select description from addons
      union
      select summary from addons
      union
      select developercomments from addons
      union
      select eula from addons
      union
      select privacypolicy from addons
      """).contents[1]
    newDB.executeSql("delete from previews")
    newDB.executeSql("delete from addons_tags")
    newDB.executeSql("delete from applications_versions")
    newDB.executeSql("delete from files")    
    newDB.executeSql("delete from versions")
    newDB.executeSql("delete from addons_users")
    newDB.executeSql("delete from addons")
  else:
    listOfTranslationsToDelete = newDB.executeSql("""
      select caption from previews where addon_id in (select id from addonSelections)
      union
      select releasenotes from versions where addon_id in (select id from addonSelections)
      union
      select name from addons where id in (select id from addonSelections)
      union 
      select homepage from addons where id in (select id from addonSelections)
      union
      select description from addons where id in (select id from addonSelections)
      union
      select summary from addons where id in (select id from addonSelections)
      union
      select developercomments from addons where id in (select id from addonSelections)
      union
      select eula from addons where id in (select id from addonSelections)
      union
      select privacypolicy from addons where id in (select id from addonSelections)
      """).contents[1]    
    newDB.executeSql("delete from previews where addon_id in (select id from addonSelections)")
    newDB.executeSql("delete from addons_tags where addon_id in (select id from addonSelections)")
    newDB.executeSql("delete from applications_versions where version_id in (select v.id from versions v where addon_id in (select id from addonSelections))")
    newDB.executeSql("delete from files where version_id in (select v.id from versions v where addon_id in (select id from addonSelections))")   
    newDB.executeSql("delete from versions where addon_id in (select id from addonSelections)")
    newDB.executeSql("delete from addons_users where addon_id in (select id from addonSelections)")
    newDB.executeSql("delete from addons where id in (select id from addonSelections)")
    
  newDB.executeManySql("delete from translations where id = %s", listOfTranslationsToDelete)
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."


#-----------------------------------------------------------------------------------------------------------
# mainToAddOns
#-----------------------------------------------------------------------------------------------------------
addonsInsertSql = """
  insert into addons (id, guid, name, addontype_id, created, homepage, description, averagerating,
                      weeklydownloads, totaldownloads, developercomments, summary, 
                      eula, privacypolicy)
  values (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)""" 
def mainToAddOns (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning mainToAddOns..." % mx.DateTime.now()
  if "all" in workingEnvironment["addons"]:
    sql = "select m.* from main m"
  else:
    sql = "select m.* from main m where m.ID in (select id from addonSelections)"  
  currentLocale = workingEnvironment["locale"]
  for a in oldDB.executeSqlNoCache(sql):
    #print a.ID, sql
    x = (a.ID, #id
         a.GUID, #guid
         addTranslation(newDB, a.Name, currentLocale), #name
         newDB.singleValueSql("select a.id from addontypes a join translations t on a.name = t.id and t.locale = 'en-US' and localized_string = '%s'" 
                              % typeEnumToTypeNameMapping[a.Type]), #addontype_id
         mx.DateTime.now(), #created
         addTranslation(newDB, a.Homepage, currentLocale), #homepage
         addTranslation(newDB, a.Description, currentLocale), #description
         a.Rating, #averagerating
         a.downloadcount, #weeklydownloads
         a.TotalDownloads, #totaldownloads
         addTranslation(newDB, a.devcomments, currentLocale), #developercomments
         addTranslation(newDB, firstWords(a.Description, 250), currentLocale), #summary
         None, #eula
         None) #privacypolicy
    newDB.executeManySql(addonsInsertSql, [ x ] )
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."
  

#-----------------------------------------------------------------------------------------------------------
# authorxrefToAddons_users
#-----------------------------------------------------------------------------------------------------------
addons_usersInsertSql = """
  insert into addons_users (addon_id, user_id)
  values (%s, %s)"""
def authorxrefToAddons_users (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning authorxrefToAddons_users..." % mx.DateTime.now()
  if "all" in workingEnvironment["addons"]:
    sql = "select * from authorxref"
  else:
    sql = "select * from authorxref where id in (select id from addonSelections)"  
  for u in oldDB.executeSqlNoCache(sql):
    newDB.executeManySql(addons_usersInsertSql, [ (u.ID, u.UserID) ] )
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."
  

#-----------------------------------------------------------------------------------------------------------
# versionToVerions
#-----------------------------------------------------------------------------------------------------------
versionsInsertSql = """
  insert into versions (id, addon_id, version, releasenotes, approvalnotes, created, modified)
  values (%s, %s, %s, %s, %s, %s, %s)""" 
applications_versionsInsertSql = """
  insert into applications_versions (application_id, version_id, min, max)
  values (%s, %s, %s, %s)""" 
applications_versionsUpdateSql = """
  update applications_versions set min = %s, max = %s where application_id =%s and version_id = %s""" 
filesInsertSql = """
  insert into files (version_id, platform_id, filename, size, hash, status, datestatuschanged, created)
  values (%s, %s, %s, %s, %s, %s, %s, %s)"""
filesUpdateSql = """
  update files set size = %s, hash = %s, status = %s, datestatuschanged = %s, created = %s 
  where version_id = %s and platform_id = %s and filename = %s"""
findAppVersionSql = """select id from appversions where application_id = %d and version = '%s'"""
findAppNameSql = """select appname from applications where AppID = %d"""
findNewAppIDSql = "select a.id from applications a join translations t on a.name = t.id and t.locale = 'en-US' and localized_string = '%s'"
appversionsInsertWithNewIDSql = """
  insert into appversions (application_id, version, created)
  values (%s, %s, %s)"""
addonUpdateForStatus = """update addons set status = %s where id = %s"""
def versionToVerions (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning versionToVerions..." % mx.DateTime.now()
  if "all" in workingEnvironment["addons"]:
    if "verbose" in workingEnvironment: print >>standardError, "\t\t\t(this may take over ten minutes)"
    # for duplicates of the same Addon, Version, OS and Application, take only the latest
    #sql = "select * from version where vID in (select max(vID) from version group by ID, Version, OSID, AppID) order by ID, vID"
    sql = "select * from version order by ID, vID"
  else:
    #sql = "select * from version where id in (select id from addonSelections) and vID in (select max(vID) from version group by ID, Version, OSID, AppID) order by ID, vID"
    sql = "select * from version where id in (select id from addonSelections) order by ID, vID"
  versionsAlreadyInserted = {}
  applications_versionsAlreadyInserted = {}
  filesAlreadyInserted = {}
  fileStatusKeyedByAddon = {}
  for v in filterVersionsAndFilesGenerator(sql, oldDB, newDB):
    #print v
    try:
      if v.ID in fileStatusKeyedByAddon:
        fileStatusKeyedByAddon[v.ID].append(yesNoEnumToTinyIntMappingForApproval[v.approved])
      else:
        fileStatusKeyedByAddon[v.ID] = [yesNoEnumToTinyIntMappingForApproval[v.approved]]
        
      if v.AppID == 79: v.AppID = 1 #hack hack hack
      if (v.ID, v.Version) not in versionsAlreadyInserted:
        newDB.executeManySql(versionsInsertSql, [ 
          (v.vID, #id
           v.ID, #addon_id
           v.Version, #version
           addTranslation(newDB, v.Notes, workingEnvironment["locale"]), #releasenotes
           "", #approvalnotes
           v.DateAdded, #created
           nullToEmptyDate(v.DateUpdated)) #modified
        ] )
        versionId = versionsAlreadyInserted[(v.ID, v.Version)] = v.vID
      else:
        versionId = versionsAlreadyInserted[(v.ID, v.Version)]
      applicationName = oldDB.singleValueSql(findAppNameSql % v.AppID)
      #newAppId = newDB.singleValueSql(findNewAppIDSql % applicationName)
      newAppId = v.newAppId
      
      appversionIdForMin = newDB.singleValueSql(findAppVersionSql % (newAppId, v.MinAppVer))
      if not appversionIdForMin:
        translatedVersion = correctAppversion(applicationName, v.MinAppVer, appversionCorrectionForMin)
        appversionIdForMin = newDB.singleValueSql(findAppVersionSql % (newAppId, translatedVersion))
        if not appversionIdForMin:
          newDB.executeManySql(appversionsInsertWithNewIDSql, [ (newAppId, translatedVersion , mx.DateTime.now()) ] )
          appversionIdForMin = newDB.singleValueSql(findAppVersionSql % (newAppId, translatedVersion))
        
      appversionIdForMax = newDB.singleValueSql(findAppVersionSql % (newAppId, v.MaxAppVer))
      if not appversionIdForMax:
        translatedVersion = correctAppversion(applicationName, v.MaxAppVer, appversionCorrectionForMax)
        appversionIdForMax = newDB.singleValueSql(findAppVersionSql % (newAppId, translatedVersion))
        if not appversionIdForMax:
          newDB.executeManySql(appversionsInsertWithNewIDSql, [ (newAppId, translatedVersion, mx.DateTime.now()) ] )
          appversionIdForMax = newDB.singleValueSql(findAppVersionSql % (newAppId, translatedVersion))
        
      if (v.AppID, versionId) not in applications_versionsAlreadyInserted:
        newDB.executeManySql(applications_versionsInsertSql, [ (v.AppID, versionId, appversionIdForMin, appversionIdForMax) ] )
        applications_versionsAlreadyInserted[(v.AppID, versionId)] = (appversionIdForMin, appversionIdForMax)
      elif applications_versionsAlreadyInserted[(v.AppID, versionId)] != (appversionIdForMin, appversionIdForMax):
        print >>standardError, "%s\tWARNING -- version ID %d of addon %s for application %d has a min/max appversion conflict" % (mx.DateTime.now(), v.vID, v.ID, v.AppID)
        print >>standardError, "                            old values: %s are superceded by new values: %s," % (applications_versionsAlreadyInserted[(v.AppID, versionId)], (appversionIdForMin, appversionIdForMax))
        newDB.executeManySql(applications_versionsUpdateSql, [ (appversionIdForMin, appversionIdForMax, v.AppID, versionId) ] )
        applications_versionsAlreadyInserted[(v.AppID, versionId)] = (appversionIdForMin, appversionIdForMax)
        
      baseFileName = os.path.basename(v.URI)
      #print "considering:", (versionId, v.OSID, baseFileName, v.AppID), v.URI
      #if (versionId, v.OSID, baseFileName, v.AppID) not in filesAlreadyInserted:
      if 'ignoreHash' not in workingEnvironment and ("recalculateHash" in workingEnvironment or v.hash == "" or v.hash is None):
        try:
          newHash = sha1Hash(v.URI, v.ID, workingEnvironment["fileCachePath"])
        except Exception, x:
          print >>standardError, "%s\tWARNING -- version ID %d of addon %s for application %d has a URL problem - %s: %s -- No hash value has been computed" % (mx.DateTime.now(), v.vID, v.ID, v.AppID, x, v.URI)
          newHash = ""
      else:
        newHash = v.hash
      if (versionId, v.OSID, baseFileName) not in filesAlreadyInserted:
        newDB.executeManySql(filesInsertSql, [ 
          (versionId, #version_id
           v.OSID, #platform_id
           baseFileName, #filename
           v.Size, #size
           newHash, #hash
           yesNoEnumToTinyIntMappingForApproval[v.approved], #status
           nullToEmptyDate(v.DateApproved), #datestatuschanged
           mx.DateTime.now()) #created
        ] )
        #filesAlreadyInserted[(versionId, v.OSID, baseFileName, v.AppID)] = None
        filesAlreadyInserted[(versionId, v.OSID, baseFileName)] = None
      else:
        #print >>standardError, "%s\tWARNING -- version ID %d of addon %s for application %d has a duplicate file - the later one supercedes the older one" % (mx.DateTime.now(), versionId, v.ID, v.AppID)
        newDB.executeManySql(filesUpdateSql, [ 
          (v.Size, #size
           newHash, #hash
           yesNoEnumToTinyIntMappingForApproval[v.approved], #status
           nullToEmptyDate(v.DateApproved), #datestatuschanged
           mx.DateTime.now(), #created
           versionId, #version_id
           v.OSID, #platform_id
           baseFileName ) #filename
        ] )
      newDB.commit()
    except KeyboardInterrupt, x:
      raise x
    except Exception, x:
      print >>standardError, "%s\tWARNING -- version ID %d of addon %s for application %d fails to migrate.\n\t\t\t%s" % (mx.DateTime.now(), v.vID, v.ID, v.AppID, x)
      traceback.print_exc(file=standardError)
      newDB.rollback()
  
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tupdating addon status..."
  try:
    for addonId, listOfFileApprovals in fileStatusKeyedByAddon.iteritems():
      #print addonId, listOfFileApprovals, 
      if 4 in listOfFileApprovals:  #if any files approved
        newAddonStatus = workingEnvironment["addonStatusWhenSomeFilesApproved"]
        #print "any", newAddonStatus
      else:
        newAddonStatus = workingEnvironment["addonStatusWhenNoFilesApproved"]
        #print "all", newAddonStatus
      newDB.executeManySql (addonUpdateForStatus, [(newAddonStatus, addonId)])
  except Exception, x:
    newDB.rollback()
    raise x
  newDB.commit()
  
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."
  
def onlyFirefoxUnlessThereIsNoFirefox (versionFileListForOneVersion):
  
  differentFilesFound = False
  try:
    initialURI = versionFileListForOneVersion[0].URI
  except IndexError:
    return
  for x in versionFileListForOneVersion:
    differentFilesFound = initialURI != x.URI
    if differentFilesFound: break
  
  if differentFilesFound:
    firefoxFound = False
    for aVersionFile in versionFileListForOneVersion:
      if aVersionFile.AppID == 1:   #Firefox
        #print "FF only", aVersionFile
        #if len(versionFileListForOneVersion) > 1:
        #  print aVersionFile.ID, aVersionFile.Version
        yield aVersionFile
        firefoxFound = True
    if not firefoxFound:
      for aVersionFile in versionFileListForOneVersion:
        #print "NO FF", aVersionFile
        yield aVersionFile
  else:
    for aVersionFile in versionFileListForOneVersion:
      #print "NO FF", aVersionFile
      yield aVersionFile

def filterVersionsAndFilesGenerator (sqlToFetchVersionFiles, oldDB, newDB):
  previousVersion = None
  currentVersion = None
  versionFileList = []
  for aVersionFileRow in oldDB.executeSqlNoCache(sqlToFetchVersionFiles):
    applicationName = oldDB.singleValueSql(findAppNameSql % aVersionFileRow.AppID)
    newAppId = newDB.singleValueSql(findNewAppIDSql % applicationName)
    aVersionFileRow.__dict__["newAppId"] = newAppId
    currentVersion = (aVersionFileRow.ID, aVersionFileRow.Version, aVersionFileRow.OSID)
    if previousVersion != currentVersion:
      for x in onlyFirefoxUnlessThereIsNoFirefox(versionFileList):
        yield x
      previousVersion = currentVersion
      versionFileList = []
    versionFileList.append(aVersionFileRow)
  for x in onlyFirefoxUnlessThereIsNoFirefox(versionFileList):
    yield x
  
#-----------------------------------------------------------------------------------------------------------
# categoryxrefToAddons_tags
#-----------------------------------------------------------------------------------------------------------
addons_tagsInsertSql = """
  insert into addons_tags (addon_id, tag_id)
  values (%s, %s)""" 
def categoryxrefToAddons_tags (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning categoryxrefToAddons_tags..." % mx.DateTime.now()
  if "all" in workingEnvironment["addons"]:
    sql = "select * from categoryxref"
  else:
    sql = "select * from categoryxref where id in (select id from addonSelections)"  
  for c in oldDB.executeSqlNoCache(sql):
    #print c.ID, c.CategoryID
    try:
      newDB.executeManySql(addons_tagsInsertSql, [ (c.ID, c.CategoryID) ] )
    except:
      pass
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."

  
#-----------------------------------------------------------------------------------------------------------
# previewsToPreviews
#-----------------------------------------------------------------------------------------------------------
previewsInsertSql = """
  insert into previews (id, addon_id, filedata, filetype, thumbdata, thumbtype, caption, highlight, created)
  values (%s, %s, %s, %s, %s, %s, %s, %s, %s)""" 
def previewsToPreviews (oldDB, newDB, workingEnvironment):
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning previewsToPreviews..." % mx.DateTime.now()
  if "all" in workingEnvironment["addons"]:
    sql = "select * from previews"
  else:
    sql = "select * from previews where id in (select id from addonSelections)"  
  for p in oldDB.executeSqlNoCache(sql):
    contentType = thumbType = ""
    content = thumbContent = None    
    if 'noPreviewImageProcessing' not in workingEnvironment:
      try:
        content, contentType, thumbContent, thumbType = imageAndThumbFromURL("%s%s" % (workingEnvironment["previewURIPrefix"], p.PreviewURI))
      except Exception, x:
        print >>standardError, "%s\tWARNING -- Error downloading %s%s for addon_id %d, preview.id %s - %s" % (mx.DateTime.now(), workingEnvironment["previewURIPrefix"], p.PreviewURI, p.ID, p.PreviewID, x)
        
    newDB.executeManySql(previewsInsertSql, [ 
      (p.PreviewID, #id
       p.ID, #addon_id
       content, #filedata
       contentType, #filetype
       thumbContent, #thumbdata
       thumbType, #thumbtype
       addTranslation(newDB, p.caption, workingEnvironment["locale"]), #caption
       yesNoEnumToTinyIntMappingForHighlight[p.preview], #caption
       mx.DateTime.now()) #created
    ] )  
  newDB.commit()
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."

  
#-----------------------------------------------------------------------------------------------------------
# setupAddonSelectionTable
#-----------------------------------------------------------------------------------------------------------
def setupAddonSelectionTable(oldDB, newDB, workingEnvironment):
  if "all" in workingEnvironment["addons"]: return
  
  if "verbose" in workingEnvironment: print >>standardError, "%s\tbeginning setupAddonSelectionTable..." % mx.DateTime.now()
  
  cleanupAddonSelectionTable(oldDB, newDB, workingEnvironment)
  oldDB.executeSql("create table addonSelections (id int, primary key (id))")
  newDB.executeSql("create table addonSelections (id int, primary key (id))")

  try:
    listOfAddons = [x.strip() for x in workingEnvironment["addons"].split(",")]
    if "not" in workingEnvironment:
      for anAddon in oldDB.executeSqlNoCache("select ID from main"):
        if str(anAddon.ID) not in listOfAddons:
          oldDB.executeSql("insert into addonSelections (id) values (%d)" % anAddon.ID)
          newDB.executeSql("insert into addonSelections (id) values (%d)" % anAddon.ID)
    else:
      oldDB.executeManySql("insert into addonSelections (id) values (%s)", listOfAddons)
      newDB.executeManySql("insert into addonSelections (id) values (%s)", listOfAddons)
  except Exception, x:
    cleanupAddonSelectionTable(oldDB, newDB, workingEnvironment)
    raise x
    
  if "verbose" in workingEnvironment: print >>standardError, "\t\t\tdone."
  
  
#-----------------------------------------------------------------------------------------------------------
# cleanupAddonSelectionTable
#-----------------------------------------------------------------------------------------------------------
def cleanupAddonSelectionTable (oldDB, newDB, workingEnvironment):
  if "all" in workingEnvironment["addons"]: return
  oldDB.executeSql("drop table if exists addonSelections")
  newDB.executeSql("drop table if exists addonSelections")


#-----------------------------------------------------------------------------------------------------------
# cleanMetaDataTables_test
#-----------------------------------------------------------------------------------------------------------
def cleanMetaDataTables_test(newDB, workingEnvironment):
  print >>standardError, "cleanMetaDataTables_test"
  errorCount = 0
  try:
    cleanAddonsTables(newDB, workingEnvironment)
    cleanMetaDataTables(newDB, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** cleanMetaDataTables: %s" % x
    errorCount += 1
  try:
    if newDB.singleValueSql("select count(*) from tags") != 0:
      print >>standardError, "  ***Failure*** cleanMetaDataTables: tags table not cleared"
      errorCount += 1
    if newDB.singleValueSql("select count(*) from appversions") != 0:
      print >>standardError, "  ***Failure*** cleanMetaDataTables: appversions table not cleared"
      errorCount += 1
    if newDB.singleValueSql("select count(*) from applications") != 0:
      print >>standardError, "  ***Failure*** cleanMetaDataTables: applications table not cleared"
      errorCount += 1
    if newDB.singleValueSql("select count(*) from platforms") != 0:
      print >>standardError, "  ***Failure*** cleanMetaDataTables: platforms table not cleared"
      errorCount += 1
    if newDB.singleValueSql("select count(*) from addons_users") != 0:
      print >>standardError, "  ***Failure*** cleanMetaDataTables: addons_users table not cleared"
      errorCount += 1
    if newDB.singleValueSql("select count(*) from users") != 0:
      print >>standardError, "  ***Failure*** cleanMetaDataTables: users table not cleared"
      errorCount += 1
    #if newDB.singleValueSql("select count(*) from addontypes") != 2:
    #  print >>standardError, "  ***Failure*** cleanMetaDataTables: addontypes not initialized properly"
    #  errorCount += 1
  except Exception, x:
    print >>standardError, "  ***Failure*** cleanMetaDataTables: Testing system failure: %s" % x
  print >>standardError, "  %d errors" % errorCount

  
#-----------------------------------------------------------------------------------------------------------
# applicationsToApplications_test
#-----------------------------------------------------------------------------------------------------------
def applicationsToApplications_test(oldDB, newDB, workingEnvironment):
  print >>standardError, "applicationsToApplications_test"
  errorCount = 0
  try:
    cleanMetaDataTables(newDB, workingEnvironment)
    applicationsToApplications(oldDB, newDB, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** applicationsToApplications: %s" % x
    errorCount += 1
  try:
    for anApplication in oldDB.executeSqlNoCache("select distinct AppName from applications"):
      if newDB.singleValueSql("select count(*) from applications a join translations t on a.name = t.id and t.locale = 'en-US' where t.localized_string = '%s'" % anApplication.AppName) != 1:
        print >>standardError, "  ***Failure*** applicationsToApplications: %s not in new database" % anApplication.AppName
        errorCount += 1
    for anApplication in oldDB.executeSqlNoCache("select * from applications"):
      sql = """
        select 
          count(*) 
        from 
          applications a join appversions av on a.id = av.application_id
        where av.id = %d""" % anApplication.AppID
      result = newDB.executeSql(sql)
      if len(result.content) == 0:
        print >>standardError, "  ***Failure*** applicationsToApplications: %s - %s %s not in new database" % (anApplication.AppID, anApplication.AppName, anApplication.Version)
        errorCount += 1
      if len(result.content) > 1:
        print >>standardError, "  ***Failure*** applicationsToApplications: %s - %s %s duplicated in new database" % (anApplication.AppID, anApplication.AppName, anApplication.Version)
        errorCount += 1
  except Exception, x:
    print >>standardError, "  ***Failure*** applicationsToApplications: Testing system failure: %s" % x
  print >>standardError, "  %d errors" % errorCount
  
  
#-----------------------------------------------------------------------------------------------------------
# categoriesToTags_test
#-----------------------------------------------------------------------------------------------------------
def categoriesToTags_test(oldDB, newDB, workingEnvironment):
  print >>standardError, "categoriesToTags_test"
  errorCount = 0
  try:
    cleanMetaDataTables(newDB, workingEnvironment)
    applicationsToApplications(oldDB, newDB, workingEnvironment)
    categoriesToTags(oldDB, newDB, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** categoriesToTags: %s" % x
    errorCount += 1
  try:
    for x in zip(oldDB.executeSql("select * from categories order by CategoryID"),
                 newDB.executeSql("select * from tags order by id")):
      if (x[0].CategoryID != x[1].id):
        print >>standardError, "  ***Failure*** categoriesToTags: %s - %s missing in new database" % (x[0].CategoryID, x[0].CatName)
        errorCount += 1
        break
  except Exception, x:
    print >>standardError, "  ***Failure*** categoriesToTags: Testing system failure: %s" % x
  print >>standardError, "  %d errors" % errorCount
  
  
#-----------------------------------------------------------------------------------------------------------
# osToPlatforms_test
#-----------------------------------------------------------------------------------------------------------
def osToPlatforms_test(oldDB, newDB, workingEnvironment):
  print >>standardError, "osToPlatforms_test"
  errorCount = 0
  try:
    cleanMetaDataTables(newDB, workingEnvironment)
    osToPlatforms(oldDB, newDB, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** osToPlatforms: %s" % x
    errorCount += 1
  try:
    for x in zip(oldDB.executeSql("select * from os order by OSID"),
                 newDB.executeSql("select * from platforms order by id")):
      if (x[0].OSID != x[1].id):
        print >>standardError, "  ***Failure*** osToPlatforms: %s - %s missing in new database" % (x[0].OSID, x[0].OSName)
        errorCount += 1
        break
  except Exception, x:
    print >>standardError, "  ***Failure*** osToPlatforms: Testing system failure: %s" % x
  print >>standardError, "  %d errors" % errorCount
  
  
#-----------------------------------------------------------------------------------------------------------
# userprofilesToUsers_test
#-----------------------------------------------------------------------------------------------------------
def userprofilesToUsers_test(oldDB, newDB, workingEnvironment):
  print >>standardError, "userprofilesToUsers_test"
  errorCount = 0
  try:
    cleanMetaDataTables(newDB, workingEnvironment)
    userprofilesToUsers(oldDB, newDB, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** userprofilesToUsers: %s" % x
    errorCount += 1
  try:
    for x in zip(oldDB.executeSql("select * from userprofiles order by UserID"),
                 newDB.executeSql("select * from users order by id")):
      if (x[0].UserID != x[1].id):
        print >>standardError, "  ***Failure*** userprofilesToUsers: %s - %s missing in new database" % (x[0].UserID, x[0].UserName)
        errorCount += 1
        break
  except Exception, x:
    print >>standardError, "  ***Failure*** userprofilesToUsers: Testing system failure: %s" % x
  print >>standardError, "  %d errors" % errorCount
  
  
#-----------------------------------------------------------------------------------------------------------
# cleanAddonsTables_test
#-----------------------------------------------------------------------------------------------------------
def cleanAddonsTables_test(newDB, workingEnvironment):
  print >>standardError, 'cleanAddonsTables_test ("all" phase)'
  errorCount = 0
  theTables = [ "previews", "addons_tags", "applications_versions", "files", "versions", "addons_users", "addons" ]
  try:
    workingEnvironment["addons"] = "all"
    cleanAddonsTables(newDB, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** cleanAddonsTables: %s" % x
    errorCount += 1
  try:
    for aTableName in theTables:
      if newDB.singleValueSql("select count(*) from %s" % aTableName) != 0:
        print >>standardError, "  ***Failure*** cleanAddonsTables: %s table not cleared" % aTableName
        errorCount += 1
  except Exception, x:
    print >>standardError, '  ***Failure*** cleanAddonsTables: Testing system failure during "all" phase: %s' % x
  print >>standardError, "  %d errors" % errorCount

  print >>standardError, 'cleanAddonsTables_test ("subset" phase)'
  errorCount = 0
  try:
    workingEnvironment["addons"] = "all"
    cleanAddonsTables(newDB, workingEnvironment)
    cleanMetaDataTables(newDB, workingEnvironment)
    applicationsToApplications (oldDatabase, newDatabase, workingEnvironment)
    categoriesToTags (oldDatabase, newDatabase, workingEnvironment)
    osToPlatforms (oldDatabase, newDatabase, workingEnvironment)
    userprofilesToUsers (oldDatabase, newDatabase, workingEnvironment)
    mainToAddOns (oldDatabase, newDatabase, workingEnvironment)
    authorxrefToAddons_users (oldDatabase, newDatabase, workingEnvironment)
    versionToVerions (oldDatabase, newDatabase, workingEnvironment)
    categoryxrefToAddons_tags (oldDatabase, newDatabase, workingEnvironment)
    previewsToPreviews (oldDatabase, newDatabase, workingEnvironment)
    
    delimitedListOfAddons = workingEnvironment["addons"] = "3110,3112,3115"
    delimitedListOfVersionIdsThatShouldBeDeleted = ",".join((str(x.id) for x in newDB.executeSql("select v.id from versions v where addon_id in (%s)" %  delimitedListOfAddons)))
    setupAddonSelectionTable(oldDatabase, newDatabase, workingEnvironment)
    try:
      cleanAddonsTables(newDB, workingEnvironment)
    finally:
      cleanupAddonSelectionTable (oldDatabase, newDatabase, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** cleanAddonsTables: %s" % x
    errorCount += 1
  try:
    if newDB.singleValueSql("select count(*) from addons where id in (%s)" % delimitedListOfAddons) != 0:
      print >>standardError, "  ***Failure*** cleanAddonsTables: one or more of deleted addon_id values %s found in addons table" % delimitedListOfAddons
      errorCount += 1
    if newDB.singleValueSql("select count(*) from versions where addon_id in (%s)" % delimitedListOfAddons) != 0:
      print >>standardError, "  ***Failure*** cleanAddonsTables: one or more of deleted addon_id values %s found in versions table" % delimitedListOfAddons
      errorCount += 1
    if newDB.singleValueSql("select count(*) from applications_versions where version_id in (%s)" % delimitedListOfVersionIdsThatShouldBeDeleted) != 0:
      print >>standardError, "  ***Failure*** cleanAddonsTables: one or more of deleted version values %s found in 	applications_versions table" % delimitedListOfVersionIdsThatShouldBeDeleted
      errorCount += 1
    if newDB.singleValueSql("select count(*) from files where version_id in (%s)" % delimitedListOfVersionIdsThatShouldBeDeleted) != 0:
      print >>standardError, "  ***Failure*** cleanAddonsTables: one or more of deleted version values %s found in files table" % delimitedListOfVersionIdsThatShouldBeDeleted
      errorCount += 1
    if newDB.singleValueSql("select count(*) from addons_tags where addon_id in (%s)" % delimitedListOfAddons) != 0:
      print >>standardError, "  ***Failure*** cleanAddonsTables: one or more of deleted addon_id values %s found in addons_tags table" % delimitedListOfAddons
      errorCount += 1
    if newDB.singleValueSql("select count(*) from previews where addon_id in (%s)" % delimitedListOfAddons) != 0:
      print >>standardError, "  ***Failure*** cleanAddonsTables: one or more of deleted addon_id values %s found in previews table" % delimitedListOfAddons
      errorCount += 1
  except Exception, x:
    print >>standardError, '  ***Failure*** cleanAddonsTables: Testing system failure during "subset" phase: %s' % x
    traceback.print_exc(file=standardError)
  print >>standardError, "  %d errors" % errorCount
  
  
#-----------------------------------------------------------------------------------------------------------
# mainToAddOnsTables_test
#-----------------------------------------------------------------------------------------------------------

def mainToAddOnsTables_test  (oldDB, newDB, workingEnvironment):
  print >>standardError, 'mainToAddOns_test ("all" phase)'
  errorCount = 0
  theTables = [ "previews", "addons_tags", "applications_versions", "files", "versions", "addons_users", "addons" ]
  try:
    workingEnvironment["addons"] = "all"
    cleanAddonsTables(newDB, workingEnvironment)
    cleanMetaDataTables(newDB, workingEnvironment)
    applicationsToApplications (oldDatabase, newDatabase, workingEnvironment)
    categoriesToTags (oldDatabase, newDatabase, workingEnvironment)
    osToPlatforms (oldDatabase, newDatabase, workingEnvironment)
    userprofilesToUsers (oldDatabase, newDatabase, workingEnvironment)
    
    mainToAddOns (oldDatabase, newDatabase, workingEnvironment)
    authorxrefToAddons_users (oldDatabase, newDatabase, workingEnvironment)
    versionToVerions (oldDatabase, newDatabase, workingEnvironment)
    categoryxrefToAddons_tags (oldDatabase, newDatabase, workingEnvironment)
    previewsToPreviews (oldDatabase, newDatabase, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** mainToAddOns: %s" % x
    errorCount += 1
  try:
    # check addons Table
    for anAddonFromOldDB in oldDB.executeSql("select * from main"):
      try:
        newDB.executeSql("select * from addons where id = %d" % anAddonFromOldDB.ID).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: addon %d missing from new database "all" phase' % anAddonFromOldDB.ID
        errorCount += 1
    # check addons_users Table
    for anAuthorxrefFromOldDB in oldDB.executeSql("select * from authorxref"):
      try:
        newDB.executeSql("select * from addons_users where addon_id = %d and user_id = %d" % (anAuthorxrefFromOldDB.ID, anAuthorxrefFromOldDB.UserID)).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: addons_users %d, %d missing from new database "all" phase' % (anAuthorxrefFromOldDB.ID, anAuthorxrefFromOldDB.UserID)
        errorCount += 1    
    # check versions, files, applications_versions tables
    ##TESTED ONLY IN SUBSET PHASE##
    #for aVersionFromOldDB in oldDB.executeSql("select * from version"):
    #  try:
    #    aVersionFromNewDB = newDB.executeSql("select * from versions where id = %d" % aVersionFromOldDB.vID).__iter__().next()
    #  except StopIteration:
    #    print >>standardError, '  ***Failure*** mainToAddOns: versions %d missing from new database "all" phase' % aVersionFromOldDB.vID
    #    errorCount += 1
    #  try:
    #    newDB.executeSql("select * from applications_versions where application_id = %d and version_id = %d" % (aVersionFromOldDB.AppID, aVersionFromOldDB.vID)).__iter__().next()
    #  except StopIteration:
    #    print >>standardError, '  ***Failure*** mainToAddOns: applications_versions %d, %d missing from new database "all" phase' % (aVersionFromOldDB.AppID, aVersionFromOldDB.vID)
    #   errorCount += 1
    # try:
    #    newDB.executeSql("select * from files where version_id = %d" % aVersionFromOldDB.vID).__iter__().next()
    #  except StopIteration:
    #    print >>standardError, '  ***Failure*** mainToAddOns: files entry for version %d missing from new database "all" phase' % aVersionFromOldDB.vID
    #    errorCount += 1
    # check categoryxref Table
    for aCategoryxrefFromOldDB in oldDB.executeSql("select * from categoryxref"):
      try:
        newDB.executeSql("select * from addons_tags where addon_id = %d and tag_id = %d" % (aCategoryxrefFromOldDB.ID, aCategoryxrefFromOldDB.CategoryID)).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: addons_tags %d, %d missing from new database "all" phase' % (aCategoryxrefFromOldDB.ID, aCategoryxrefFromOldDB.CategoryID)
        errorCount += 1    
    # check previews Table
    for aPreviewsFromOldDB in oldDB.executeSql("select * from previews"):
      try:
        newDB.executeSql("select * from previews where id = %d " % aPreviewsFromOldDB.PreviewID).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: previews %d missing from new database "all" phase' % aPreviewsFromOldDB.PreviewID
        errorCount += 1    
  except Exception, x:
    print >>standardError, '  ***Failure*** mainToAddOns: Testing system failure during "all" phase: %s' % x
  print >>standardError, "  %d errors" % errorCount

  print >>standardError, 'mainToAddOns_test ("subset" phase)'
  errorCount = 0
  try:
    workingEnvironment["addons"] = "all"
    cleanAddonsTables(newDB, workingEnvironment)
    cleanMetaDataTables(newDB, workingEnvironment)
    
    applicationsToApplications (oldDatabase, newDatabase, workingEnvironment)
    categoriesToTags (oldDatabase, newDatabase, workingEnvironment)
    osToPlatforms (oldDatabase, newDatabase, workingEnvironment)
    userprofilesToUsers (oldDatabase, newDatabase, workingEnvironment)
    
    delimitedListOfAddons = workingEnvironment["addons"] = "3110,3112,3115"
    delimitedListOfVersionIdsThatShouldBeDeleted = ",".join((str(x.id) for x in newDB.executeSql("select v.id from versions v where addon_id in (%s)" %  delimitedListOfAddons)))
    setupAddonSelectionTable(oldDatabase, newDatabase, workingEnvironment)
  
    try:
      mainToAddOns (oldDatabase, newDatabase, workingEnvironment)
      authorxrefToAddons_users (oldDatabase, newDatabase, workingEnvironment)
      versionToVerions (oldDatabase, newDatabase, workingEnvironment)
      categoryxrefToAddons_tags (oldDatabase, newDatabase, workingEnvironment)
      previewsToPreviews (oldDatabase, newDatabase, workingEnvironment)
    finally:
      cleanupAddonSelectionTable (oldDatabase, newDatabase, workingEnvironment)
  except Exception, x:
    print >>standardError, "  ***Failure*** mainToAddOns: %s" % x
    errorCount += 1
  try:
    # check addons Table
    for anAddonFromOldDB in oldDB.executeSql("select * from main where id in (%s)" % delimitedListOfAddons):
      try:
        newDB.executeSql("select * from addons where id = %d" % anAddonFromOldDB.ID).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: addon %d missing from new database "subset" phase' % anAddonFromOldDB.ID
        errorCount += 1
    # check addons_users Table
    for anAuthorxrefFromOldDB in oldDB.executeSql("select * from authorxref where id in (%s)" % delimitedListOfAddons):
      try:
        newDB.executeSql("select * from addons_users where addon_id = %d and user_id = %d" % (anAuthorxrefFromOldDB.ID, anAuthorxrefFromOldDB.UserID)).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: addons_users %d, %d missing from new database "subset" phase' % (anAuthorxrefFromOldDB.ID, anAuthorxrefFromOldDB.UserID)
        errorCount += 1    
    # check versions, files, applications_versions tables
    for aVersionFromOldDB in oldDB.executeSql("select * from version where id in (%s)" % delimitedListOfAddons):
      try:
        aVersionFromNewDB = newDB.executeSql("select * from versions where id = %d" % aVersionFromOldDB.vID).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: versions %d missing from new database "subset" phase' % aVersionFromOldDB.vID
        errorCount += 1
      try:
        newDB.executeSql("select * from applications_versions where application_id = %d and version_id = %d" % (aVersionFromOldDB.AppID, aVersionFromOldDB.vID)).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: applications_versions %d, %d missing from new database "subset" phase' % (aVersionFromOldDB.AppID, aVersionFromOldDB.vID)
        errorCount += 1
      try:
        newDB.executeSql("select * from files where version_id = %d" % aVersionFromOldDB.vID).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: files entry for version %d missing from new database "subset" phase' % aVersionFromOldDB.vID
        errorCount += 1
    # check categoryxref Table
    for aCategoryxrefFromOldDB in oldDB.executeSql("select * from categoryxref where id in (%s)" % delimitedListOfAddons):
      try:
        newDB.executeSql("select * from addons_tags where addon_id = %d and tag_id = %d" % (aCategoryxrefFromOldDB.ID, aCategoryxrefFromOldDB.CategoryID)).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: addons_tags %d, %d missing from new database "all" phase' % (aCategoryxrefFromOldDB.ID, aCategoryxrefFromOldDB.CategoryID)
        errorCount += 1    
    # check previews Table
    for aPreviewsFromOldDB in oldDB.executeSql("select * from previews where id in (%s)" % delimitedListOfAddons):
      try:
        newDB.executeSql("select * from previews where id = %d " % aPreviewsFromOldDB.PreviewID).__iter__().next()
      except StopIteration:
        print >>standardError, '  ***Failure*** mainToAddOns: previews %d missing from new database "all" phase' % aPreviewsFromOldDB.PreviewID
        errorCount += 1    
  except Exception, x:
    print >>standardError, '  ***Failure*** mainToAddOns: Testing system failure during "subset" phase: %s' % x
    traceback.print_exc(file=standardError)
  print >>standardError, "  %d errors" % errorCount

  
#-----------------------------------------------------------------------------------------------------------
# runTests
#-----------------------------------------------------------------------------------------------------------
def runTests (oldDB, newDB, workingEnvironment):
  cleanMetaDataTables_test(newDB, workingEnvironment)
  applicationsToApplications_test(oldDB, newDB, workingEnvironment)
  categoriesToTags_test(oldDB, newDB, workingEnvironment)
  osToPlatforms_test(oldDB, newDB, workingEnvironment)
  userprofilesToUsers_test(oldDB, newDB, workingEnvironment)
  cleanAddonsTables_test(newDB, workingEnvironment)
  mainToAddOnsTables_test(oldDB, newDB, workingEnvironment)
  
#-----------------------------------------------------------------------------------------------------------
# addBogusTranslations
#-----------------------------------------------------------------------------------------------------------
def addBogusTranslations (newDB):
  counter = 1
  for aTranslationIDRow in newDB.executeSqlNoCache("select distinct id from translations"):
    for aLocale in ['de', 'ru', 'nl']:
      newDB.executeManySql("insert into translations (id, locale, localized_string) values (%s, %s, %s)", [(aTranslationIDRow.id, aLocale, "bogus translation #%06d" % counter)])
      newDB.commit()
    counter += 1                                                                                                    
  
#-----------------------------------------------------------------------------------------------------------
# removeBogusTranslations
#-----------------------------------------------------------------------------------------------------------
def removeBogusTranslations (newDB):
  newDB.executeSql ("delete from translations where localized_string like 'bogus translation #%'")
  newDB.commit()
  
#===========================================================================================================
# main
#===========================================================================================================
if __name__ == "__main__":

  import cse.ConfigurationManager
  
  try:
  
    options = [ ('?',  'help', False, None, 'print this message'), 
                ('c',  'config', True, './migration.conf', 'specify the location and name of the config file'),
                (None, 'oldDatabaseName', True, "", 'the name of the old database within the server'),
                (None, 'oldServerName', True, "", 'the name of the old database server'),
                (None, 'oldUserName', True, "", 'the name of the user in the old database'),
                (None, 'oldPassword', True, "", 'the password for the user in the old database'),
                (None, 'newDatabaseName', True, "", 'the name of the new database within the server'),
                (None, 'newServerName', True, "", 'the name of the new database server'),
                (None, 'newUserName', True, "", 'the name of the user in the new database'),
                (None, 'newPassword', True, "", 'the password for the user in the new database'),
                ('a',  'addons', True, "all", 'a quoted comma delimited list of the ids of addons OR the word "all"'),
                ('n',  'not', False, None, """reverses the meaning of the "addon" option.  If the "addon" option has a list, then specify everything except what's on the list"""),
                ('M',  'migrateMetaData', False, None, 'if present, this switch causes the metadata tables to be migrated'),
                ('A',  'migrateAddons', False, None, 'if present, this switch causes the addons in the "addons" option list to be migrated'),
                ('v',  'verbose', False, None, 'print status information as it runs to stderr'),
                ('l',  'locale', True, "en-US", 'set the locale for addons being migrated'),
                (None, 'clear', False, None, 'clear all exisiting information from the new database'),
                (None, 'clearAddons', False, None, 'clear all exisiting addons in the "addons" option list information from the new database'),
                (None, 'test', False, None, 'run the test cases'),
                (None, 'show', False, None, 'echo the configuration to stdout and then quit'),
                (None, 'bogusTranslationsInsert', False, None, 'fill out the translations table with a full set of locales for all entries for load testing'),
                (None, 'bogusTranslationsRemove', False, None, 'remove any bogus translations from the translations table'),
                (None, 'logPathName', True, "./migration.log", 'a progressive log of all runs of the migration script'),
                (None, 'addonStatusWhenNoFilesApproved', True, "sandbox", 'the status to set an addon if no files approved (null, sandbox, pending, nominated, public, disabled)'),
                (None, 'addonStatusWhenSomeFilesApproved', True, "public", 'the status to set an addon if some files approved (null, sandbox, pending, nominated, public, disabled)'),
                (None, 'noPreviewImageProcessing', False, "", 'do not process preview images'),
                (None, 'previewURIPrefix', True, "https://addons.mozilla.org", 'a prefix to add to the URI in the preview table to enable downloading'),
                (None, 'fileCachePath', True, "", 'a path for the caching of files (blank for no cache)'),
                (None, 'recalculateHash', False, "", 'force the recaclulation of the hash for the xpi files'),
                (None, 'ignoreHash', False, "", 'no not allow the migration to touch the hash'),

              ]
    
    workingEnvironment = cse.ConfigurationManager.ConfigurationManager(options)
    #workingEnvironment["version"] = version
    
    if 'help' in workingEnvironment:
      print >>standardError, "migration %s\nThis routine migrates data from the old AMO database schema to the new one." % version
      workingEnvironment.outputCommandSummary(standardError, 1)
      sys.exit()
      
    if 'show' in workingEnvironment:
      workingEnvironment.output(sys.stdout)
      sys.exit()
    
  except cse.ConfigurationManager.ConfigurationManagerNotAnOption, x:
      print >>standardError, "m1 %s\n%s\nFor usage, try --help" % (version, x)
      sys.exit()
  
    
  try:
    useLogFile = open(workingEnvironment["logPathName"], "a")
    print >>useLogFile, mx.DateTime.now()
    workingEnvironment.output(useLogFile)
    useLogFile.close()

    if "verbose" in workingEnvironment: 
      print >>standardError, "%s beginning migration version %s with options:" % (mx.DateTime.now(), version)
      workingEnvironment.output(standardError)

    validStatusStates = {"null": 0, "sandbox": 1, "pending": 2, "nominated": 3, "public": 4, "disabled": 5}
    try:
      workingEnvironment["addonStatusWhenNoFilesApproved"] = validStatusStates[workingEnvironment["addonStatusWhenNoFilesApproved"]]
    except KeyError:
      print >>standardError, "%s is not a valid value for addonStatusWhenNoFilesApproved. See --help" % workingEnvironment["addonStatusWhenNoFilesApproved"]
      sys.exit()
    try:
      workingEnvironment["addonStatusWhenSomeFilesApproved"] = validStatusStates[workingEnvironment["addonStatusWhenSomeFilesApproved"]]
    except KeyError:
      print >>standardError, "%s is not a valid value for addonStatusWhenSomeFilesApproved. See --help" % workingEnvironment["addonStatusWhenSomeFilesApproved"]
      sys.exit()

    oldDatabase = cse.MySQLDatabase.MySQLDatabase(workingEnvironment["oldDatabaseName"], workingEnvironment["oldServerName"], 
                                                  workingEnvironment["oldUserName"], workingEnvironment["oldPassword"])
    newDatabase = cse.MySQLDatabase.MySQLDatabase(workingEnvironment["newDatabaseName"], workingEnvironment["newServerName"], 
                                                  workingEnvironment["newUserName"], workingEnvironment["newPassword"])

    if "test" in workingEnvironment:
      runTests(oldDatabase, newDatabase, workingEnvironment)
    else:
      setupAddonSelectionTable(oldDatabase, newDatabase, workingEnvironment)
      
      try:
        if "clear" in workingEnvironment:
          originalAddonsOption = workingEnvironment["addons"]
          workingEnvironment["addons"] = "all"
          cleanAddonsTables(newDatabase, workingEnvironment)
          cleanMetaDataTables(newDatabase, workingEnvironment)
          workingEnvironment["addons"] = originalAddonsOption
        elif "clearAddons" in workingEnvironment:
          cleanAddonsTables(newDatabase, workingEnvironment)
    
        if "migrateMetaData" in workingEnvironment:
          if "verbose" in workingEnvironment: print >>standardError, "%s beginning metadata migration" % mx.DateTime.now()
          try:
            cleanMetaDataTables (newDatabase, workingEnvironment)
          except:
            raise MigrationException("Migrating MetaData implies clearing MetaData tables, but there appears to be Addon data in the database.  Clear the Addon data before trying to migrate MetaData.")
          applicationsToApplications (oldDatabase, newDatabase, workingEnvironment)
          categoriesToTags (oldDatabase, newDatabase, workingEnvironment)
          osToPlatforms (oldDatabase, newDatabase, workingEnvironment)
          userprofilesToUsers (oldDatabase, newDatabase, workingEnvironment)
          
        if "migrateAddons" in workingEnvironment:
          if "verbose" in workingEnvironment: print >>standardError, "%s beginning addons migration" % mx.DateTime.now()
          cleanAddonsTables (newDatabase, workingEnvironment)
          mainToAddOns (oldDatabase, newDatabase, workingEnvironment)
          categoryxrefToAddons_tags (oldDatabase, newDatabase, workingEnvironment)
          authorxrefToAddons_users (oldDatabase, newDatabase, workingEnvironment)
          versionToVerions (oldDatabase, newDatabase, workingEnvironment)
          #categoryxrefToAddons_tags (oldDatabase, newDatabase, workingEnvironment)
          previewsToPreviews (oldDatabase, newDatabase, workingEnvironment)
          
        if "bogusTranslationsInsert" in workingEnvironment:
          if "verbose" in workingEnvironment: print >>standardError, "%s creating bogus translations" % mx.DateTime.now()
          addBogusTranslations(newDatabase)
        
        if "bogusTranslationsRemove" in workingEnvironment:
          if "verbose" in workingEnvironment: print >>standardError, "%s removing bogus translations" % mx.DateTime.now()
          removeBogusTranslations(newDatabase)
          
      finally:
        cleanupAddonSelectionTable (oldDatabase, newDatabase, workingEnvironment)
        
  except KeyboardInterrupt:
    print >>standardError, "Interrupted..."
    pass
  
  except MigrationException, x:
    print >>standardError, x
    
  except Exception, x:
    print >>standardError, x
    traceback.print_exc(file=standardError)
  
  if "verbose" in workingEnvironment: print >>standardError, "done."
