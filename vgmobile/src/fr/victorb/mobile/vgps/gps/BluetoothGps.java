/*
 * BluetoothGps.java
 *
 * Created on November 15, 2007, 9:44 PM
 *
 * To change this template, choose Tools | Template Manager
 * and open the template in the editor.
 */

package fr.victorb.mobile.vgps.gps;

import fr.victorb.mobile.utils.Split;
import java.io.IOException;
import java.io.InputStream;
import javax.microedition.io.Connector;

/**
 *
 * @author Victor
 */
public class BluetoothGps extends Gps implements Runnable {

    private InputStream gpsStream;
    private boolean connected;
    private String url = new String();
    private GpsPosition position = new GpsPosition();
    private int elevation = 0;   
    
    /** Creates a new instance of BluetoothGps */
    public BluetoothGps() {
        super();
    }

    public boolean start(String url) {
        this.url = url;
        connected = true;
        try {
            new Thread(this).start();
        } catch (Exception ex) {            
        }
        return false;
    }

    public void stop() {
        connected = false;       
    }
    
    public GpsPosition getPosition() {       
        return position;
    }
    
    public void run() {
        char c;
        Split split;
        String string;
        
        try {            
            gpsStream = Connector.openInputStream(url);    
            connected = true;
        } catch (IOException ex) {            
        }
               
        while (connected) {
            StringBuffer buffer = new StringBuffer();
            try {
                while((c = (char)gpsStream.read()) != '$'){}
                while((c = (char)gpsStream.read()) != 10) {
                    buffer.append(c);
                }
                
                String nmea = buffer.toString();

                if (nmea.startsWith("GPGGA")) {
                    // Use GPGGA messages to get the validity of the fix
                    // and the elevation
                    boolean valid = false;
                    split = new Split(nmea);
                    split.next();       // GPGGA
                    split.next();       // time
                    split.next();       // lat
                    split.next();       // N/S
                    split.next();       // lng
                    split.next();       // E/W
                    string = split.next();                    
                    valid = (Integer.parseInt(string)) > 0;  // fix valid                    
                    System.out.println("valid :" + string + " -> " + valid);
                    split.next();       // nb satellites
                    split.next();       // h dilution
                    elevation =(int) Float.parseFloat(split.next()); // elevation                           
                    updatefixValid(valid);
                } else if (nmea.startsWith("GPRMC")) {

                    
                    split = new Split(nmea);
                    split.next();           //GPRMC
                    string = split.next();    //time
                    split.next();           // status
                    position.time.hour = Integer.parseInt(string.substring(0, 2));
                    position.time.minute = Integer.parseInt(string.substring(2, 4));
                    position.time.second = Integer.parseInt(string.substring(4, 6));                    
                    position.latitude = Float.parseFloat(split.next()) * (split.next().toUpperCase().equals("N")?1:-1) / 100;
                    position.longitude = Float.parseFloat(split.next()) * (split.next().toUpperCase().equals("E")?1:-1) / 100;
                    position.elevation = elevation;                    
                    split.next();           // speed    
                    split.next();           // degrees
                    string = split.next();
                    position.date.day = Integer.parseInt(string.substring(0, 2));
                    position.date.month = Integer.parseInt(string.substring(2, 4));
                    position.date.year = Integer.parseInt(string.substring(4, 6));                    
                    updatePosition(position);
                }
                
            } catch (Exception ex) {
                System.out.println("Parsing Exception" + ex.getMessage());
            }            
        }
        try {
            gpsStream.close();                
        } catch (Exception e) {                
        }           
    }
}
