#pragma once
#include <Arduino.h>
#include <Wire.h>

// ============================================================================
// Self-contained hardware drivers for INA219, DS18B20 (OneWire), and JSON
// Used automatically when external third-party libraries are not installed.
// ============================================================================

#if __has_include(<Adafruit_INA219.h>)
  #include <Adafruit_INA219.h>
#else
class Adafruit_INA219 {
public:
  explicit Adafruit_INA219(uint8_t addr = 0x40) : _addr(addr), _wire(&Wire) {}

  bool begin(TwoWire* theWire = &Wire) {
    _wire = theWire;
    _wire->beginTransmission(_addr);
    if (_wire->endTransmission() != 0) return false;
    setCalibration_32V_2A();
    return true;
  }

  void setCalibration_32V_2A() {
    writeReg16(0x05, 4096);   // Cal register for 0.1 ohm shunt, 100 uA/bit current LSB
    writeReg16(0x00, 0x399F); // 32V bus, +/-320mV shunt, 12-bit continuous
  }

  float getBusVoltage_V() {
    uint16_t raw = readReg16(0x02);
    return (float)((int16_t)(raw >> 3)) * 0.004f;
  }

  float getCurrent_mA() {
    writeReg16(0x05, 4096);
    int16_t raw = (int16_t)readReg16(0x04);
    return (float)raw * 0.1f;
  }

  float getPower_mW() {
    writeReg16(0x05, 4096);
    uint16_t raw = readReg16(0x03);
    return (float)raw * 2.0f;
  }

private:
  void writeReg16(uint8_t reg, uint16_t val) {
    _wire->beginTransmission(_addr);
    _wire->write(reg);
    _wire->write((uint8_t)(val >> 8));
    _wire->write((uint8_t)(val & 0xFF));
    _wire->endTransmission();
  }

  uint16_t readReg16(uint8_t reg) {
    _wire->beginTransmission(_addr);
    _wire->write(reg);
    if (_wire->endTransmission(false) != 0) return 0;
    if (_wire->requestFrom(_addr, (uint8_t)2, (uint8_t)true) < 2) return 0;
    uint16_t hi = _wire->read();
    uint16_t lo = _wire->read();
    return (hi << 8) | lo;
  }

  uint8_t _addr;
  TwoWire* _wire;
};
#endif

#if __has_include(<OneWire.h>) && __has_include(<DallasTemperature.h>)
  #include <OneWire.h>
  #include <DallasTemperature.h>
#else
#ifndef DEVICE_DISCONNECTED_C
  #define DEVICE_DISCONNECTED_C -127.0f
#endif

class OneWire {
public:
  explicit OneWire(uint8_t pin) : _pin(pin) {
    pinMode(_pin, INPUT_PULLUP);
  }

  bool reset() {
    pinMode(_pin, OUTPUT);
    digitalWrite(_pin, LOW);
    delayMicroseconds(480);
    pinMode(_pin, INPUT_PULLUP);
    delayMicroseconds(70);
    bool presence = (digitalRead(_pin) == LOW);
    delayMicroseconds(410);
    return presence;
  }

  void writeByte(uint8_t v) {
    for (uint8_t mask = 0x01; mask; mask <<= 1) {
      if (v & mask) {
        pinMode(_pin, OUTPUT);
        digitalWrite(_pin, LOW);
        delayMicroseconds(6);
        pinMode(_pin, INPUT_PULLUP);
        delayMicroseconds(64);
      } else {
        pinMode(_pin, OUTPUT);
        digitalWrite(_pin, LOW);
        delayMicroseconds(60);
        pinMode(_pin, INPUT_PULLUP);
        delayMicroseconds(10);
      }
    }
  }

  uint8_t readByte() {
    uint8_t r = 0;
    for (uint8_t mask = 0x01; mask; mask <<= 1) {
      pinMode(_pin, OUTPUT);
      digitalWrite(_pin, LOW);
      delayMicroseconds(3);
      pinMode(_pin, INPUT_PULLUP);
      delayMicroseconds(10);
      if (digitalRead(_pin)) r |= mask;
      delayMicroseconds(53);
    }
    return r;
  }

private:
  uint8_t _pin;
};

class DallasTemperature {
public:
  explicit DallasTemperature(OneWire* ow) : _ow(ow), _count(0) {}

  void begin() {
    _count = _ow->reset() ? 1 : 0;
  }

  void setResolution(uint8_t) {}
  void setWaitForConversion(bool) {}

  int getDeviceCount() {
    _count = _ow->reset() ? 1 : 0;
    return _count;
  }

  void requestTemperatures() {
    if (!_ow->reset()) return;
    _ow->writeByte(0xCC); // Skip ROM
    _ow->writeByte(0x44); // Convert T
  }

  float getTempCByIndex(uint8_t) {
    if (!_ow->reset()) return DEVICE_DISCONNECTED_C;
    _ow->writeByte(0xCC); // Skip ROM
    _ow->writeByte(0xBE); // Read Scratchpad
    uint8_t lsb = _ow->readByte();
    uint8_t msb = _ow->readByte();
    if (lsb == 0xFF && msb == 0xFF) return DEVICE_DISCONNECTED_C;
    int16_t raw = (int16_t)((msb << 8) | lsb);
    return (float)raw / 16.0f;
  }

private:
  OneWire* _ow;
  int _count;
};
#endif

