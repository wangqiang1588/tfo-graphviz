=== TFO Graphviz ===
Contributors: chrisy
Donate link: http://blog.flirble.org/donate
Tags: graphviz, flirble
Requires at least: 3.0.1
Tested up to: 3.0.1
Stable tag: 1.0

Generates Graphviz graphics using shortcodes. Supports almost all Graphviz features.

== Description ==

[Graphviz](http://www.graphviz.org/) is a powerful tool for visualising network and tree structures the connect objects.

This WordPress plugin provides a shortcode mechanism to create Graphviz graphics within blogs, including image map generation and most
other GraphViz features.

== Installation ==

Installation is simple. Either install from directly within WordPress or:

1. Download and unzip the plugin to the `/wp-content/plugins/tfo-graphviz` directory within your WordPress installation.
1. Make the directory `/wp-content/tfo-graphviz` and make it writeable by the web server - this is where generated images and image maps go.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Use the `[graphviz]` shortcode to generate graphs.

You will also need GraphViz installed on the host. See the FAQ and http://www.graphviz.org/Download.php .

== Frequently Asked Questions ==

= What is Graphviz? =

[Graphviz](http://www.graphviz.org/) is a way of generating visualisations of structural relationships between objects. Almost any kind of diagram where something _connects_ to something else can be drawn and automatically laid out using the language DOT.


= How do I use this plugin? =

Use the `[graphviz]` shortcode. Various uses are explained in the "_How to use_" section.

= How to I write DOT? =

The online documentation for [Graphviz](http://www.graphviz.org/) is terse and not especially helpful, in particular the [DOT language](http://www.graphviz.org/doc/info/lang.html) page is only helpful if you happen to be able to read and approximation of [BNF](http://en.wikipedia.org/wiki/Backus%E2%80%93Naur_Form).

There are however several other introductions to Graphviz and DOT, including [an excerpt on the O'Reilly LinuxDevCenter.com site](http://linuxdevcenter.com/pub/a/linux/2004/05/06/graphviz_dot.html). Another approach would be to look at the examples in the [Graphviz gallery](http://www.graphviz.org/Gallery.php).

= How do I install GraphViz =

This depends on your host. You will find some details at http://www.graphviz.org/Download.php but many systems also have it in their own
package management system, for example this is package `graphviz` on Debian, Ubuntu and Fedora systems.

I may later provide a web-based system to produce graphics on demand, for a nominal fee.

== Screenshots ==

1. [Virtual Disk Stack](screenshot-1.png) 
   This image was generated with the following markup:

`
    [graphviz lang="dot" output="png" simple="yes" imap="yes" href="self" title="TFO Graphviz Demo"]
    
    style=filled; bgcolor="#f1f1f1";
    fontsize=10; labeljust=l;
    
    node [style="rounded,filled", color=lightblue2, fontsize=10, shape=box];
    edge [arrowhead=vee, arrowsize=0.5];
    
    subgraph cluster_client {
      node [label="File system", URL="http://en.wikipedia.org/wiki/Xfs"]; fs;
      node [label="LVM", URL="http://en.wikipedia.org/wiki/Logical_Volume_Manager_(Linux)"]; lvm;
      node [label="Linux HBA driver", URL="http://www.ibm.com/developerworks/linux/library/l-scsi-subsystem/"]; clienthba;
      fs -> lvm -> clienthba;
      bgcolor=lightgrey;
      label = "Virtual Machine";
      URL = "http://www.ubuntu.com/";
    }
    
    subgraph cluster_esxi {
      node [label="Virtual HBA hardware"]; virtualhba;
      node [label="VMDK file"]; vmdk;
      node [label="vmfs"]; vmfs;
      node [label="ESXi HBA driver"]; vmhba;
      virtualhba -> vmdk -> vmfs -> vmhba;
      bgcolor=white;
      label = "Hypervisor";
      URL = "http://www.vmware.com/products/vsphere-hypervisor/";
    }
    
    subgraph cluster_hardware {
      node [label="Physical HBA hardware", URL="http://tinyurl.com/dellpercraid"]; phba;
      node [label="Physical disks", URL="http://www.amazon.com/gp/product/B002B7EIVC?ie=UTF8&tag=clblog01-20&linkCode=as2&camp=1789&creative=390957&creativeASIN=B002B7EIVC"]; disks;
      phba -> disks;
      style=filled; bgcolor=lightgrey;
      label = "Hardware";
      URL = "http://www.dell.com/us/business/p/poweredge-r515/pd";
    }
    
    clienthba -> virtualhba;
    vmhba -> phba;
    label = "I/O stack";
    [/graphviz]
`


== Changelog ==

= 1.0 =
* First release.

== Upgrade Notice ==

Nothing to upgrade, yet!

== How to use TFO Graphviz ==

The shortcode syntax is:

`
[graphviz <options>]
 <DOT code>
[/graphviz]
`

Where `<options>` is anything from this list. All are entirely optional:

* `href="self|`*&lt;URL&gt;*`"`

  Encompasses the generated image with a link either to the image itself (with the `self` value) or to the provided URL. If the
  option is empty (for example, `href=""`) then no link is generated. This is the default.

* `id="`*&lt;id&gt;*`"`

  Provides the identifier used to link the generated image to an image map. If you use the `simple` option then it also
  provides the name of the generated DOT graph container (since GraphViz uses this to generate the image map). If not given
  then an identifier is generated with the form `tfo_graphviz_N` where *N* is an integer that starts at one when the plugin
  is loaded and is incremented with use.

* `imap="yes|no"`

  GraphViz can generate image maps using any URL's given in the DOT code so that clicking on objects in the resultant image
  will direct a web browser to a new page. The effect of this option is to both instruct GraphViz to generate a client-side
  image map and to also insert that map into the generated HTML. It will use the `id` value as the name of the map (see the
  `id` option for details). `imap` defaults to `no`.

* `lang="<dot|neato|twopi|circo|fdp>"`

  Specifies the particular GraphViz interpreter to use. The options are `dot`, `neato`, `twopi`, `circo` and `fdp`.
  The default is `dot`.

* `output="<png|gif|jpg>"`

  Indicates the desired image format. Defaults to `png`.

* `simple="yes|no"`

  The `simple` option provides a very basic DOT wrapper around your code such that the following is possible:
 
  `
  [graphviz simple="yes"] a -> b -> c; [/graphviz]
  `

  The generated code would look like:

  `
  digraph tfo_graphviz_1 {
      a -> b -> c;
  }
  `

  See the `id` option for a description of where the name of the `digraph` comes from. `simple` defaults to `no`.

* `title="`*&lt;title&gt;*`"`

  Indicates the title of the image. This is used in the `alt` and `title` attributes of the image reference. This
  defaults to an empty string. Note that image maps may indicate a `title` string which will appear in tool-tips.


