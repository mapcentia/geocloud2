CREATE OR REPLACE FUNCTION voronoi(table_name text,geom_col text) returns SETOF record as $$

#############################################################################
#
# Voronoi diagram calculator/ Delaunay triangulator
# Translated to Python by Bill Simons
# September, 2005
#
# Additional changes by Carson Farmer added November 2010
#
# Converted to pl/python function by Darrell Fuhriman, April 2012
# based on code from:
# https://svn.osgeo.org/qgis/trunk/qgis/python/plugins/fTools/tools/voronoi.py
#
# Calculate Delaunay triangulation or the Voronoi polygons for a set of
# 2D input points.
#
# Derived from code bearing the following notice:
#
#  The author of this software is Steven Fortune.  Copyright (c) 1994 by AT&T
#  Bell Laboratories.
#  Permission to use, copy, modify, and distribute this software for any
#  purpose without fee is hereby granted, provided that this entire notice
#  is included in all copies of any software which is or includes a copy
#  or modification of this software and in all copies of the supporting
#  documentation for such software.
#  THIS SOFTWARE IS BEING PROVIDED "AS IS", WITHOUT ANY EXPRESS OR IMPLIED
#  WARRANTY.  IN PARTICULAR, NEITHER THE AUTHORS NOR AT&T MAKE ANY
#  REPRESENTATION OR WARRANTY OF ANY KIND CONCERNING THE MERCHANTABILITY
#  OF THIS SOFTWARE OR ITS FITNESS FOR ANY PARTICULAR PURPOSE.
#
# Comments were incorporated from Shane O'Sullivan's translation of the
# original code into C++ (http://mapviewer.skynet.ie/voronoi.html)
#
# Steve Fortune's homepage: http://netlib.bell-labs.com/cm/cs/who/sjf/index.html
#
#############################################################################

import math
import sys
import getopt
TOLERANCE = 1e-9
BIG_FLOAT = 1e38

#------------------------------------------------------------------
class Context(object):
    def __init__(self):
        self.doPrint = 0
        self.debug   = 0
        self.plot    = 0
        self.triangulate = False
        self.vertices  = []    # list of vertex 2-tuples: (x,y)
        self.lines     = []    # equation of line 3-tuple (a b c), for the equation of the line a*x+b*y = c
        self.edges     = []    # edge 3-tuple: (line index, vertex 1 index, vertex 2 index)   if either vertex index is -1, the edge extends to infiinity
        self.triangles = []    # 3-tuple of vertex indices
        self.polygons  = {}    # a dict of site:[edges] pairs

    def circle(self,x,y,rad):
        pass

    def clip_line(self,edge):
        pass

    def line(self,x0,y0,x1,y1):
        pass

    def outSite(self,s):
        if(self.debug):
            print "site (%d) at %f %f" % (s.sitenum, s.x, s.y)
        elif(self.triangulate):
            pass
        elif(self.plot):
            self.circle (s.x, s.y, cradius)
        elif(self.doPrint):
            print "s %f %f" % (s.x, s.y)

    def outVertex(self,s):
        self.vertices.append((s.x,s.y))
        if(self.debug):
            print  "vertex(%d) at %f %f" % (s.sitenum, s.x, s.y)
        elif(self.triangulate):
            pass
        elif(self.doPrint and not self.plot):
            print "v %f %f" % (s.x,s.y)

    def outTriple(self,s1,s2,s3):
        self.triangles.append((s1.sitenum, s2.sitenum, s3.sitenum))
        if(self.debug):
            print "circle through left=%d right=%d bottom=%d" % (s1.sitenum, s2.sitenum, s3.sitenum)
        elif(self.triangulate and self.doPrint and not self.plot):
            print "%d %d %d" % (s1.sitenum, s2.sitenum, s3.sitenum)

    def outBisector(self,edge):
        self.lines.append((edge.a, edge.b, edge.c))
        if(self.debug):
            print "line(%d) %gx+%gy=%g, bisecting %d %d" % (edge.edgenum, edge.a, edge.b, edge.c, edge.reg[0].sitenum, edge.reg[1].sitenum)
        elif(self.triangulate):
            if(self.plot):
                self.line(edge.reg[0].x, edge.reg[0].y, edge.reg[1].x, edge.reg[1].y)
        elif(self.doPrint and not self.plot):
            print "l %f %f %f" % (edge.a, edge.b, edge.c)

    def outEdge(self,edge):
        sitenumL = -1
        if edge.ep[Edge.LE] is not None:
            sitenumL = edge.ep[Edge.LE].sitenum
        sitenumR = -1
        if edge.ep[Edge.RE] is not None:
            sitenumR = edge.ep[Edge.RE].sitenum
        if edge.reg[0].sitenum not in self.polygons:
            self.polygons[edge.reg[0].sitenum] = []
        if edge.reg[1].sitenum not in self.polygons:
            self.polygons[edge.reg[1].sitenum] = []
        self.polygons[edge.reg[0].sitenum].append((edge.edgenum,sitenumL,sitenumR))
        self.polygons[edge.reg[1].sitenum].append((edge.edgenum,sitenumL,sitenumR))
        self.edges.append((edge.edgenum,sitenumL,sitenumR))
        if(not self.triangulate):
            if self.plot:
                self.clip_line(edge)
            elif(self.doPrint):
                print "e %d" % edge.edgenum,
                print " %d " % sitenumL,
                print "%d" % sitenumR

#------------------------------------------------------------------
def voronoi(siteList,context):
    try:
      edgeList  = EdgeList(siteList.xmin,siteList.xmax,len(siteList))
      priorityQ = PriorityQueue(siteList.ymin,siteList.ymax,len(siteList))
      siteIter = siteList.iterator()

      bottomsite = siteIter.next()
      context.outSite(bottomsite)
      newsite = siteIter.next()
      minpt = Site(-BIG_FLOAT,-BIG_FLOAT)
      while True:
          if not priorityQ.isEmpty():
              minpt = priorityQ.getMinPt()

          if (newsite and (priorityQ.isEmpty() or cmp(newsite,minpt) < 0)):
              # newsite is smallest -  this is a site event
              context.outSite(newsite)

              # get first Halfedge to the LEFT and RIGHT of the new site
              lbnd = edgeList.leftbnd(newsite)
              rbnd = lbnd.right

              # if this halfedge has no edge, bot = bottom site (whatever that is)
              # create a new edge that bisects
              bot  = lbnd.rightreg(bottomsite)
              edge = Edge.bisect(bot,newsite)
              context.outBisector(edge)

              # create a new Halfedge, setting its pm field to 0 and insert
              # this new bisector edge between the left and right vectors in
              # a linked list
              bisector = Halfedge(edge,Edge.LE)
              edgeList.insert(lbnd,bisector)

              # if the new bisector intersects with the left edge, remove
              # the left edge's vertex, and put in the new one
              p = lbnd.intersect(bisector)
              if p is not None:
                  priorityQ.delete(lbnd)
                  priorityQ.insert(lbnd,p,newsite.distance(p))

              # create a new Halfedge, setting its pm field to 1
              # insert the new Halfedge to the right of the original bisector
              lbnd = bisector
              bisector = Halfedge(edge,Edge.RE)
              edgeList.insert(lbnd,bisector)

              # if this new bisector intersects with the right Halfedge
              p = bisector.intersect(rbnd)
              if p is not None:
                  # push the Halfedge into the ordered linked list of vertices
                  priorityQ.insert(bisector,p,newsite.distance(p))

              newsite = siteIter.next()

          elif not priorityQ.isEmpty():
              # intersection is smallest - this is a vector (circle) event

              # pop the Halfedge with the lowest vector off the ordered list of
              # vectors.  Get the Halfedge to the left and right of the above HE
              # and also the Halfedge to the right of the right HE
              lbnd  = priorityQ.popMinHalfedge()
              llbnd = lbnd.left
              rbnd  = lbnd.right
              rrbnd = rbnd.right

              # get the Site to the left of the left HE and to the right of
              # the right HE which it bisects
              bot = lbnd.leftreg(bottomsite)
              top = rbnd.rightreg(bottomsite)

              # output the triple of sites, stating that a circle goes through them
              mid = lbnd.rightreg(bottomsite)
              context.outTriple(bot,top,mid)

              # get the vertex that caused this event and set the vertex number
              # couldn't do this earlier since we didn't know when it would be processed
              v = lbnd.vertex
              siteList.setSiteNumber(v)
              context.outVertex(v)

              # set the endpoint of the left and right Halfedge to be this vector
              if lbnd.edge.setEndpoint(lbnd.pm,v):
                  context.outEdge(lbnd.edge)

              if rbnd.edge.setEndpoint(rbnd.pm,v):
                  context.outEdge(rbnd.edge)


              # delete the lowest HE, remove all vertex events to do with the
              # right HE and delete the right HE
              edgeList.delete(lbnd)
              priorityQ.delete(rbnd)
              edgeList.delete(rbnd)


              # if the site to the left of the event is higher than the Site
              # to the right of it, then swap them and set 'pm' to RIGHT
              pm = Edge.LE
              if bot.y > top.y:
                  bot,top = top,bot
                  pm = Edge.RE

              # Create an Edge (or line) that is between the two Sites.  This
              # creates the formula of the line, and assigns a line number to it
              edge = Edge.bisect(bot, top)
              context.outBisector(edge)

              # create a HE from the edge
              bisector = Halfedge(edge, pm)

              # insert the new bisector to the right of the left HE
              # set one endpoint to the new edge to be the vector point 'v'
              # If the site to the left of this bisector is higher than the right
              # Site, then this endpoint is put in position 0; otherwise in pos 1
              edgeList.insert(llbnd, bisector)
              if edge.setEndpoint(Edge.RE - pm, v):
                  context.outEdge(edge)

              # if left HE and the new bisector don't intersect, then delete
              # the left HE, and reinsert it
              p = llbnd.intersect(bisector)
              if p is not None:
                  priorityQ.delete(llbnd);
                  priorityQ.insert(llbnd, p, bot.distance(p))

              # if right HE and the new bisector don't intersect, then reinsert it
              p = bisector.intersect(rrbnd)
              if p is not None:
                  priorityQ.insert(bisector, p, bot.distance(p))
          else:
              break

      he = edgeList.leftend.right
      while he is not edgeList.rightend:
          context.outEdge(he.edge)
          he = he.right
      Edge.EDGE_NUM = 0
    except Exception, err:
      print "######################################################"
      print str(err)

#------------------------------------------------------------------
def isEqual(a,b,relativeError=TOLERANCE):
    # is nearly equal to within the allowed relative error
    norm = max(abs(a),abs(b))
    return (norm < relativeError) or (abs(a - b) < (relativeError * norm))

#------------------------------------------------------------------
class Site(object):
    def __init__(self,x=0.0,y=0.0,sitenum=0):
        self.x = x
        self.y = y
        self.sitenum = sitenum

    def dump(self):
        print "Site #%d (%g, %g)" % (self.sitenum,self.x,self.y)

    def __cmp__(self,other):
        if self.y < other.y:
            return -1
        elif self.y > other.y:
            return 1
        elif self.x < other.x:
            return -1
        elif self.x > other.x:
            return 1
        else:
            return 0

    def distance(self,other):
        dx = self.x - other.x
        dy = self.y - other.y
        return math.sqrt(dx*dx + dy*dy)

#------------------------------------------------------------------
class Edge(object):
    LE = 0
    RE = 1
    EDGE_NUM = 0
    DELETED = {}   # marker value

    def __init__(self):
        self.a = 0.0
        self.b = 0.0
        self.c = 0.0
        self.ep  = [None,None]
        self.reg = [None,None]
        self.edgenum = 0

    def dump(self):
        print "(#%d a=%g, b=%g, c=%g)" % (self.edgenum,self.a,self.b,self.c)
        print "ep",self.ep
        print "reg",self.reg

    def setEndpoint(self, lrFlag, site):
        self.ep[lrFlag] = site
        if self.ep[Edge.RE - lrFlag] is None:
            return False
        return True

    @staticmethod
    def bisect(s1,s2):
        newedge = Edge()
        newedge.reg[0] = s1 # store the sites that this edge is bisecting
        newedge.reg[1] = s2

        # to begin with, there are no endpoints on the bisector - it goes to infinity
        # ep[0] and ep[1] are None

        # get the difference in x dist between the sites
        dx = float(s2.x - s1.x)
        dy = float(s2.y - s1.y)
        adx = abs(dx)  # make sure that the difference in positive
        ady = abs(dy)

        # get the slope of the line
        newedge.c = float(s1.x * dx + s1.y * dy + (dx*dx + dy*dy)*0.5)
        if adx > ady :
            # set formula of line, with x fixed to 1
            newedge.a = 1.0
            newedge.b = dy/dx
            newedge.c /= dx
        else:
            # set formula of line, with y fixed to 1
            newedge.b = 1.0
            newedge.a = dx/dy
            newedge.c /= dy

        newedge.edgenum = Edge.EDGE_NUM
        Edge.EDGE_NUM += 1
        return newedge


#------------------------------------------------------------------
class Halfedge(object):
    def __init__(self,edge=None,pm=Edge.LE):
        self.left  = None   # left Halfedge in the edge list
        self.right = None   # right Halfedge in the edge list
        self.qnext = None   # priority queue linked list pointer
        self.edge  = edge   # edge list Edge
        self.pm     = pm
        self.vertex = None  # Site()
        self.ystar  = BIG_FLOAT

    def dump(self):
        print "Halfedge--------------------------"
        print "left: ",    self.left
        print "right: ",   self.right
        print "edge: ",    self.edge
        print "pm: ",      self.pm
        print "vertex: ",
        if self.vertex: self.vertex.dump()
        else: print "None"
        print "ystar: ",   self.ystar


    def __cmp__(self,other):
        if self.ystar > other.ystar:
            return 1
        elif self.ystar < other.ystar:
            return -1
        elif self.vertex.x > other.vertex.x:
            return 1
        elif self.vertex.x < other.vertex.x:
            return -1
        else:
            return 0

    def leftreg(self,default):
        if not self.edge:
            return default
        elif self.pm == Edge.LE:
            return self.edge.reg[Edge.LE]
        else:
            return self.edge.reg[Edge.RE]

    def rightreg(self,default):
        if not self.edge:
            return default
        elif self.pm == Edge.LE:
            return self.edge.reg[Edge.RE]
        else:
            return self.edge.reg[Edge.LE]


    # returns True if p is to right of halfedge self
    def isPointRightOf(self,pt):
        e = self.edge
        topsite = e.reg[1]
        right_of_site = pt.x > topsite.x

        if(right_of_site and self.pm == Edge.LE):
            return True

        if(not right_of_site and self.pm == Edge.RE):
            return False

        if(e.a == 1.0):
            dyp = pt.y - topsite.y
            dxp = pt.x - topsite.x
            fast = 0;
            if ((not right_of_site and e.b < 0.0) or (right_of_site and e.b >= 0.0)):
                above = dyp >= e.b * dxp
                fast = above
            else:
                above = pt.x + pt.y * e.b > e.c
                if(e.b < 0.0):
                    above = not above
                if (not above):
                    fast = 1
            if (not fast):
                dxs = topsite.x - (e.reg[0]).x
                above = e.b * (dxp*dxp - dyp*dyp) < dxs*dyp*(1.0+2.0*dxp/dxs + e.b*e.b)
                if(e.b < 0.0):
                    above = not above
        else:  # e.b == 1.0
            yl = e.c - e.a * pt.x
            t1 = pt.y - yl
            t2 = pt.x - topsite.x
            t3 = yl - topsite.y
            above = t1*t1 > t2*t2 + t3*t3

        if(self.pm==Edge.LE):
            return above
        else:
            return not above

    #--------------------------
    # create a new site where the Halfedges el1 and el2 intersect
    def intersect(self,other):
        e1 = self.edge
        e2 = other.edge
        if (e1 is None) or (e2 is None):
            return None

        # if the two edges bisect the same parent return None
        if e1.reg[1] is e2.reg[1]:
            return None

        d = e1.a * e2.b - e1.b * e2.a
        if isEqual(d,0.0):
            return None

        xint = (e1.c*e2.b - e2.c*e1.b) / d
        yint = (e2.c*e1.a - e1.c*e2.a) / d
        if(cmp(e1.reg[1],e2.reg[1]) < 0):
            he = self
            e = e1
        else:
            he = other
            e = e2

        rightOfSite = xint >= e.reg[1].x
        if((rightOfSite     and he.pm == Edge.LE) or
           (not rightOfSite and he.pm == Edge.RE)):
            return None

        # create a new site at the point of intersection - this is a new
        # vector event waiting to happen
        return Site(xint,yint)



#------------------------------------------------------------------
class EdgeList(object):
    def __init__(self,xmin,xmax,nsites):
        if xmin > xmax: xmin,xmax = xmax,xmin
        self.hashsize = int(2*math.sqrt(nsites+4))

        self.xmin   = xmin
        self.deltax = float(xmax - xmin)
        self.hash   = [None]*self.hashsize

        self.leftend  = Halfedge()
        self.rightend = Halfedge()
        self.leftend.right = self.rightend
        self.rightend.left = self.leftend
        self.hash[0]  = self.leftend
        self.hash[-1] = self.rightend

    def insert(self,left,he):
        he.left  = left
        he.right = left.right
        left.right.left = he
        left.right = he

    def delete(self,he):
        he.left.right = he.right
        he.right.left = he.left
        he.edge = Edge.DELETED

    # Get entry from hash table, pruning any deleted nodes
    def gethash(self,b):
        if(b < 0 or b >= self.hashsize):
            return None
        he = self.hash[b]
        if he is None or he.edge is not Edge.DELETED:
            return he

        #  Hash table points to deleted half edge.  Patch as necessary.
        self.hash[b] = None
        return None

    def leftbnd(self,pt):
        # Use hash table to get close to desired halfedge
        bucket = int(((pt.x - self.xmin)/self.deltax * self.hashsize))

        if(bucket < 0):
            bucket =0;

        if(bucket >=self.hashsize):
            bucket = self.hashsize-1

        he = self.gethash(bucket)
        if(he is None):
            i = 1
            while True:
                he = self.gethash(bucket-i)
                if (he is not None): break;
                he = self.gethash(bucket+i)
                if (he is not None): break;
                i += 1

        # Now search linear list of halfedges for the corect one
        if (he is self.leftend) or (he is not self.rightend and he.isPointRightOf(pt)):
            he = he.right
            while he is not self.rightend and he.isPointRightOf(pt):
                he = he.right
            he = he.left;
        else:
            he = he.left
            while (he is not self.leftend and not he.isPointRightOf(pt)):
                he = he.left

        # Update hash table and reference counts
        if(bucket > 0 and bucket < self.hashsize-1):
            self.hash[bucket] = he
        return he


#------------------------------------------------------------------
class PriorityQueue(object):
    def __init__(self,ymin,ymax,nsites):
        self.ymin = ymin
        self.deltay = ymax - ymin
        self.hashsize = int(4 * math.sqrt(nsites))
        self.count = 0
        self.minidx = 0
        self.hash = []
        for i in range(self.hashsize):
            self.hash.append(Halfedge())

    def __len__(self):
        return self.count

    def isEmpty(self):
        return self.count == 0

    def insert(self,he,site,offset):
        he.vertex = site
        he.ystar  = site.y + offset
        last = self.hash[self.getBucket(he)]
        next = last.qnext
        while((next is not None) and cmp(he,next) > 0):
            last = next
            next = last.qnext
        he.qnext = last.qnext
        last.qnext = he
        self.count += 1

    def delete(self,he):
        if (he.vertex is not None):
            last = self.hash[self.getBucket(he)]
            while last.qnext is not he:
                last = last.qnext
            last.qnext = he.qnext
            self.count -= 1
            he.vertex = None

    def getBucket(self,he):
        bucket = int(((he.ystar - self.ymin) / self.deltay) * self.hashsize)
        if bucket < 0: bucket = 0
        if bucket >= self.hashsize: bucket = self.hashsize-1
        if bucket < self.minidx:  self.minidx = bucket
        return bucket

    def getMinPt(self):
        while(self.hash[self.minidx].qnext is None):
            self.minidx += 1
        he = self.hash[self.minidx].qnext
        x = he.vertex.x
        y = he.ystar
        return Site(x,y)

    def popMinHalfedge(self):
        curr = self.hash[self.minidx].qnext
        self.hash[self.minidx].qnext = curr.qnext
        self.count -= 1
        return curr


#------------------------------------------------------------------
class SiteList(object):
    def __init__(self,pointList):
        self.__sites = []
        self.__sitenum = 0

        self.__xmin = pointList[0].x
        self.__ymin = pointList[0].y
        self.__xmax = pointList[0].x
        self.__ymax = pointList[0].y
        for i,pt in enumerate(pointList):
            self.__sites.append(Site(pt.x,pt.y,i))
            if pt.x < self.__xmin: self.__xmin = pt.x
            if pt.y < self.__ymin: self.__ymin = pt.y
            if pt.x > self.__xmax: self.__xmax = pt.x
            if pt.y > self.__ymax: self.__ymax = pt.y
        self.__sites.sort()

    def setSiteNumber(self,site):
        site.sitenum = self.__sitenum
        self.__sitenum += 1

    class Iterator(object):
        def __init__(this,lst):  this.generator = (s for s in lst)
        def __iter__(this):      return this
        def next(this):
            try:
                return this.generator.next()
            except StopIteration:
                return None

    def iterator(self):
        return SiteList.Iterator(self.__sites)

    def __iter__(self):
        return SiteList.Iterator(self.__sites)

    def __len__(self):
        return len(self.__sites)

    def _getxmin(self): return self.__xmin
    def _getymin(self): return self.__ymin
    def _getxmax(self): return self.__xmax
    def _getymax(self): return self.__ymax
    xmin = property(_getxmin)
    ymin = property(_getymin)
    xmax = property(_getxmax)
    ymax = property(_getymax)

def clip_voronoi( edges, c, width, height, extent, exX, exY ):
    """ Clip voronoi function based on code written for Inkscape
        Copyright (C) 2010 Alvin Penner, penner@vaxxine.com
    """
    def clip_line( x1, y1, x2, y2, w, h, x, y ):
        if x1 < 0 - x and x2 < 0 - x:
            return [ 0, 0, 0, 0 ]
        if x1 > w + x and x2 > w + x:
            return [ 0, 0, 0, 0 ]
        if x1 < 0 - x:
            y1 = ( y1 * x2 - y2 * x1 ) / ( x2 - x1 )
            x1 = 0 - x
        if x2 < 0 - x:
            y2 = ( y1 * x2 - y2 * x1 ) / ( x2 - x1 )
            x2 = 0 - x
        if x1 > w + x:
            y1 = y1 + ( w + x - x1 ) * ( y2 - y1 ) / ( x2 - x1 )
            x1 = w + x
        if x2 > w + x:
            y2 = y1 + ( w + x - x1 ) *( y2 - y1 ) / ( x2 - x1 )
            x2 = w + x
        if y1 < 0 - y and y2 < 0 - y:
            return [ 0, 0, 0, 0 ]
        if y1 > h + y and y2 > h + y:
            return [ 0, 0, 0, 0 ]
        if x1 == x2 and y1 == y2:
            return [ 0, 0, 0, 0 ]
        if y1 < 0 - y:
            x1 = ( x1 * y2 - x2 * y1 ) / ( y2 - y1 )
            y1 = 0 - y
        if y2 < 0 - y:
            x2 = ( x1 * y2 - x2 * y1 ) / ( y2 - y1 )
            y2 = 0 - y
        if y1 > h + y:
            x1 = x1 + ( h + y - y1 ) * ( x2 - x1 ) / ( y2 - y1 )
            y1 = h + y
        if y2 > h + y:
            x2 = x1 + ( h + y - y1) * ( x2 - x1 ) / ( y2 - y1 )
            y2 = h + y
        return [ x1, y1, x2, y2 ]

    # 'lines' is a list of all points coordinate pairs of all edges
    lines = []
    hasXMin = False
    hasYMin = False
    hasXMax = False
    hasYMax = False

    for edge in edges:
        if edge[ 1 ] >= 0 and edge[ 2 ] >= 0:       # two vertices
            [ x1, y1, x2, y2 ] = clip_line( c.vertices[ edge[ 1 ] ][ 0 ], c.vertices[ edge[ 1 ] ][ 1 ], c.vertices[ edge[ 2 ] ][ 0 ], c.vertices[ edge[ 2 ] ][ 1 ], width, height, exX, exY )
        elif edge[ 1 ] >= 0:                      # only one vertex
            if c.lines[ edge[ 0 ] ][ 1 ] == 0:      # vertical line
                xtemp = c.lines[ edge[ 0 ] ][ 2 ] / c.lines[ edge[ 0 ] ][ 0 ]
                if c.vertices[ edge[ 1 ] ][ 1 ] > ( height + exY ) / 2:
                    ytemp = height + exY
                else:
                    ytemp = 0 - exX
            else:
                xtemp = width + exX
                ytemp = ( c.lines[ edge[ 0 ] ][ 2 ] - ( width + exX ) * c.lines[ edge[ 0 ] ][ 0 ] ) / c.lines[ edge[ 0 ] ][ 1 ]
            [ x1, y1, x2, y2 ] = clip_line( c.vertices[ edge[ 1 ] ][ 0 ], c.vertices[ edge[ 1 ] ][ 1 ], xtemp, ytemp, width, height, exX, exY )
        elif edge[ 2 ] >= 0:                       # only one vertex
            if c.lines[ edge[ 0 ] ][ 1 ] == 0:       # vertical line
                xtemp = c.lines[ edge[ 0 ] ][ 2 ] / c.lines[ edge[ 0 ] ][ 0 ]
                if c.vertices[ edge[ 2 ] ][ 1 ] > ( height + exY ) / 2:
                    ytemp = height + exY
                else:
                    ytemp = 0.0 - exY
            else:
                xtemp = 0.0 - exX
                ytemp = c.lines[ edge[ 0 ] ][ 2 ] / c.lines[ edge[ 0 ] ][ 1 ]
            [ x1, y1, x2, y2 ] = clip_line( xtemp, ytemp, c.vertices[ edge[ 2 ] ][ 0 ], c.vertices[ edge[ 2 ] ][ 1 ], width, height, exX, exY )
        if x1 or x2 or y1 or y2:
            lines.append( ( x1 + extent["xmin"], y1 + extent["ymin"] ) )
            lines.append( ( x2 + extent["xmin"], y2 + extent["ymin"] ) )
            if 0 - exX in ( x1, x2 ):
                hasXMin = True
            if 0 - exY in ( y1, y2 ):
                hasYMin = True
            if height + exY in ( y1, y2 ):
                hasYMax = True
            if width + exX in ( x1, x2 ):
                hasXMax = True
    if hasXMin:
        if hasYMax:
            lines.append( ( extent["xmin"] - exX, height + extent["ymin"] + exY ) )
        if hasYMin:
            lines.append( ( extent["xmin"] - exX, extent["ymin"] - exY ) )
    if hasXMax:
        if hasYMax:
            lines.append( ( width + extent["xmin"] + exX, height + extent["ymin"] + exY ) )
        if hasYMin:
            lines.append( ( width + extent["xmin"] + exX, extent["ymin"] - exY ) )

    return lines


def lines_as_wkt(lines):
    if lines:
        multipoint_wkt = ''
        for line in lines:
            multipoint_wkt += '%s %s,' % (line[0],line[1])
        return "MULTIPOINT(%s)" % multipoint_wkt[:-1]
    else:
        return None

pts = []
c=Context()
# table_name text,table_key text,geom_col text)
rv = plpy.execute("SELECT ST_SRID(%s) as srid FROM %s" % (geom_col, table_name), 1)
srid = rv[0]['srid']
if srid <= 0:
  srid = -1

# calculate extent
extent = {}
rv = plpy.execute("SELECT ST_SetSRID(ST_extent(%s), %i) as extent FROM %s" % (geom_col, srid, table_name), 1)
extentWKB = rv[0]['extent']
rv = plpy.execute("SELECT ST_XMin('%s'::geometry), ST_XMax('%s'::geometry), ST_YMin('%s'::geometry), ST_YMax('%s'::geometry)" % (extentWKB,extentWKB,extentWKB,extentWKB))
extent["xmin"] = rv[0]["st_xmin"]
extent["xmax"] = rv[0]["st_xmax"]
extent["ymin"] = rv[0]["st_ymin"]
extent["ymax"] = rv[0]["st_ymax"]

width = extent["xmax"] - extent["xmin"]
height = extent["ymax"] - extent["ymin"]

plpy.debug("got srid: %i" % srid)
plpy.debug("got extent: %s" % extent)
plpy.debug("got width: %s" % width)
plpy.debug("got height: %s" % height)

# we need to use distinct, because the code barfs if we have duplicate points
for row in plpy.execute("SELECT DISTINCT st_x(%s) as x, st_y(%s) as y FROM %s" % (geom_col, geom_col, table_name)):
  pts.append(Site(row["x"] - extent["xmin"],row["y"] - extent["ymin"]))     # note reative coordinates

# do the real work
sl = SiteList(pts)
voronoi(sl,c)

for site, edges in c.polygons.iteritems():
    # trim the extracted polygons to the extent of the input points
    lines = clip_voronoi( edges, c, width, height, extent, 0, 0 )
    lines_wkt = lines_as_wkt(lines)
    if lines_wkt is not None:
        rv = plpy.execute("SELECT ST_ConvexHull(ST_MPointFromText('%s',%i)) AS the_geom" % (lines_wkt,srid))
        yield (site+1, rv[0]['the_geom'])

$$ LANGUAGE plpythonu;


CREATE OR REPLACE FUNCTION voronoi(table_name text) returns SETOF record as $$
  SELECT * from voronoi($1, 'the_geom') as (id integer,the_geom geometry);
$$ LANGUAGE SQL;